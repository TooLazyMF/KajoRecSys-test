<?php

namespace App\Http\Controllers;

use App\Models\StudentReference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function importStudents(Request $request): RedirectResponse
    {
        $request->validate([
            'student_file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
        ]);

        $file = $request->file('student_file');
        $fileRows = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->readXlsxRows($file->getRealPath())
            : $this->readCsvRows($file->getRealPath());

        [$headerIndex, $headerMap] = $this->findStudentFileHeader($fileRows);
        $headers = $fileRows[$headerIndex] ?? [];
        $fileRows = array_slice($fileRows, $headerIndex + 1);
        $required = ['id_number', 'gender', 'year', 'course'];
        $missing = array_diff($required, array_keys($headerMap));

        $hasNameColumns = isset($headerMap['name'])
            || (isset($headerMap['first_name']) && isset($headerMap['last_name']));

        if ($missing !== [] || ! $hasNameColumns) {
            throw ValidationException::withMessages([
                'student_file' => 'The file must include Code, name columns, Sex, Year, and Course columns.',
            ]);
        }

        $rows = [];
        foreach ($fileRows as $rowIndex => $values) {
            if (count(array_filter($values, fn ($value) => filled(trim((string) $value)))) === 0) {
                continue;
            }

            $sourceData = [];
            foreach ($headers as $index => $header) {
                $sourceData[trim((string) $header)] = trim((string) ($values[$index] ?? ''));
            }

            $idNumber = StudentReference::normalizeIdNumber($values[$headerMap['id_number']] ?? '');
            $name = $this->studentNameFromRow($values, $headerMap);
            $gender = $this->normalizeGender($values[$headerMap['gender']] ?? '');

            if ($idNumber === '' || $name === '' || $gender === null) {
                continue;
            }

            $year = $this->valueFromHeader($values, $headerMap, 'year');
            if ($year === null) {
                throw ValidationException::withMessages([
                    'student_file' => "Year level is required for every student record. Missing year level for {$name} (ID {$idNumber}) on data row ".($rowIndex + $headerIndex + 2).'.',
                ]);
            }

            $rows[$idNumber] = [
                'id_number' => $idNumber,
                'name' => $name,
                'gender' => $gender,
                'year' => $this->normalizeYearLevel($year),
                'course' => $this->valueFromHeader($values, $headerMap, 'course'),
                'source_data' => json_encode($sourceData, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'student_file' => 'No valid student records were found in the file.',
            ]);
        }

        $isUpdate = StudentReference::query()->exists();

        DB::transaction(function () use ($rows): void {
            $ids = array_map('strval', array_keys($rows));

            StudentReference::query()->upsert(
                array_values($rows),
                ['id_number'],
                ['name', 'gender', 'year', 'course', 'source_data', 'updated_at']
            );

            // The uploaded file is the complete roster for the current school year.
            StudentReference::query()->whereNotIn('id_number', $ids)->delete();

            $studentUsers = User::query()
                ->where('role', 'student')
                ->whereNotNull('id_number')
                ->get(['id', 'id_number']);
            $studentUsersByNumber = $studentUsers->groupBy(
                fn (User $user) => StudentReference::normalizeIdNumber($user->id_number)
            );

            foreach ($rows as $row) {
                $matchingUserIds = $studentUsersByNumber->get($row['id_number'], collect())->pluck('id');
                if ($matchingUserIds->isNotEmpty()) {
                    User::query()->whereKey($matchingUserIds)->update([
                        'name' => $row['name'],
                        'gender' => $row['gender'],
                        'is_active' => true,
                        'is_graduated' => false,
                        'updated_at' => now(),
                    ]);
                }
            }

            $missingUserIds = $studentUsers
                ->reject(fn (User $user) => in_array(
                    StudentReference::normalizeIdNumber($user->id_number),
                    $ids,
                    true,
                ))
                ->pluck('id');
            if ($missingUserIds->isNotEmpty()) {
                User::query()
                    ->whereKey($missingUserIds)
                    ->update(['is_active' => false, 'updated_at' => now()]);
            }
        });

        return to_route('guidance.dashboard')->with('status', count($rows).($isUpdate
            ? ' student records updated. Accounts missing from the file were deactivated and moved to the archived files folder.'
            : ' student records imported successfully. Future uploads will update this roster automatically.'));
    }

    /** @return array<int, array<int, string>> */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['student_file' => 'The student file could not be read.']);
        }

        try {
            $headers = fgetcsv($handle) ?: [];
            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                $rows[] = array_map(fn ($value) => (string) $value, $values);
            }

            array_unshift($rows, array_map(fn ($header) => (string) $header, $headers));

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return array<int, array<int, string>> */
    private function readXlsxRows(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['student_file' => 'The Excel file could not be read.']);
        }

        try {
            $sheetName = collect(range(0, $zip->numFiles - 1))
                ->map(fn (int $index) => $zip->getNameIndex($index))
                ->first(fn (string $name) => preg_match('/^xl\/worksheets\/sheet\d+\.xml$/', $name) === 1);
            $sheetXml = $sheetName ? $zip->getFromName($sheetName) : false;

            if ($sheetXml === false) {
                throw ValidationException::withMessages(['student_file' => 'The Excel file has no readable worksheet.']);
            }

            $sharedStrings = [];
            if (($sharedXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $shared = simplexml_load_string($sharedXml);
                if ($shared !== false) {
                    foreach ($shared->xpath('//*[local-name() = "si"]') ?: [] as $item) {
                        $sharedStrings[] = implode('', $this->xmlTextValues($item));
                    }
                }
            }

            $sheet = simplexml_load_string($sheetXml);
            if ($sheet === false) {
                throw ValidationException::withMessages(['student_file' => 'The Excel worksheet could not be read.']);
            }

            $rows = [];
            foreach ($sheet->xpath('//*[local-name() = "sheetData"]/*[local-name() = "row"]') ?: [] as $row) {
                $values = [];
                foreach ($row->xpath('./*[local-name() = "c"]') ?: [] as $cell) {
                    $column = $this->xlsxColumnIndex((string) $cell['r']);
                    $type = (string) $cell['t'];
                    $inlineText = $cell->xpath('./*[local-name() = "is"]');
                    $valueNodes = $cell->xpath('./*[local-name() = "v"]');
                    $value = $type === 'inlineStr'
                        ? implode('', $this->xmlTextValues($inlineText[0] ?? $cell))
                        : (string) ($valueNodes[0] ?? '');

                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }

                    $values[$column] = $value;
                }

                if ($values !== [] && count(array_filter($values, fn ($value) => filled(trim((string) $value)))) > 0) {
                    $maxColumn = max(array_keys($values));
                    $rows[] = array_map(fn ($index) => (string) ($values[$index] ?? ''), range(0, $maxColumn));
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function xmlTextValues(\SimpleXMLElement $xml): array
    {
        $texts = $xml->xpath('.//*[local-name() = "t"]') ?: [];

        return array_map(fn ($text) => (string) $text, $texts);
    }

    private function xlsxColumnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    /** @param array<int, string|null> $headers */
    private function studentFileHeaderMap(array $headers): array
    {
        $aliases = [
            'id_number' => ['id', 'idno', 'idnum', 'idnumber', 'code', 'studentcode', 'studentid', 'studentno', 'studentnumber', 'studentidnumber'],
            'name' => ['name', 'fullname', 'studentname', 'studentfullname'],
            'first_name' => ['firstname', 'givenname'],
            'last_name' => ['lastname', 'surname', 'familyname'],
            'middle_name' => ['middlename', 'middleinitial'],
            'gender' => ['gender', 'sex'],
            'year' => ['year', 'years', 'yr', 'yrlevel', 'yearlevel', 'level', 'yearofstudy', 'academicyearlevel', 'currentyear', 'currentyearlevel'],
            'course' => ['course', 'program', 'degree', 'courseprogram', 'programname', 'degreeprogram'],
        ];
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $header, " \t\r\n\xEF\xBB\xBF"))) ?? '';
            foreach ($aliases as $field => $fieldAliases) {
                $isYearHeader = $field === 'year'
                    && ! str_starts_with($normalized, 'schoolyear')
                    && (str_starts_with($normalized, 'year') || str_starts_with($normalized, 'yr'));
                $isCourseHeader = $field === 'course'
                    && (str_contains($normalized, 'course') || str_contains($normalized, 'program'));

                if (in_array($normalized, $fieldAliases, true) || $isYearHeader || $isCourseHeader) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private function findStudentFileHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 20, true) as $index => $row) {
            $headerMap = $this->studentFileHeaderMap($row);

            if (
                isset($headerMap['id_number'], $headerMap['gender'], $headerMap['year'], $headerMap['course'])
                && (isset($headerMap['name']) || (isset($headerMap['first_name'], $headerMap['last_name'])))
            ) {
                return [$index, $headerMap];
            }
        }

        return [0, $this->studentFileHeaderMap($rows[0] ?? [])];
    }

    /** @param array<int, string> $values @param array<string, int> $headerMap */
    private function studentNameFromRow(array $values, array $headerMap): string
    {
        if (isset($headerMap['name'])) {
            return trim((string) ($values[$headerMap['name']] ?? ''));
        }

        return implode(' ', array_filter([
            $this->valueFromHeader($values, $headerMap, 'first_name'),
            $this->valueFromHeader($values, $headerMap, 'middle_name'),
            $this->valueFromHeader($values, $headerMap, 'last_name'),
        ]));
    }

    /** @param array<int, string> $values @param array<string, int> $headerMap */
    private function valueFromHeader(array $values, array $headerMap, string $field): ?string
    {
        return isset($headerMap[$field]) && filled($value = trim((string) ($values[$headerMap[$field]] ?? '')))
            ? $value
            : null;
    }

    private function normalizeYearLevel(string $year): string
    {
        $year = trim($year);
        if (! ctype_digit($year) || (int) $year < 1 || (int) $year > 8) {
            return $year;
        }

        $number = (int) $year;
        $suffix = match (true) {
            $number % 100 >= 11 && $number % 100 <= 13 => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix.' Year';
    }

    private function normalizeGender(string $gender): ?string
    {
        return match (strtolower(trim($gender))) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            default => null,
        };
    }

    public function index(): View
    {
        $studentReferences = StudentReference::query()
            ->get(['id_number', 'course', 'year'])
            ->keyBy(fn (StudentReference $reference) => StudentReference::normalizeIdNumber($reference->id_number));

        $students = User::query()
            ->where('role', 'student')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'id_number', 'gender', 'is_active', 'is_graduated'])
            ->filter(fn (User $student) => filled(
                $studentReferences->get(StudentReference::normalizeIdNumber($student->id_number))?->year
            ))
            ->values();

        $teachers = User::query()
            ->where('role', 'teacher')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.users', compact('students', 'studentReferences', 'teachers'));
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, ['student', 'teacher'], true), 404);

        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in($user->role === 'student'
                    ? ['activate', 'deactivate', 'graduate']
                    : ['activate', 'deactivate']),
            ],
        ]);

        match ($validated['action']) {
            'activate' => $user->forceFill(['is_active' => true])->save(),
            'deactivate' => $user->forceFill(['is_active' => false])->save(),
            'graduate' => $user->forceFill(['is_graduated' => true, 'is_active' => false])->save(),
        };

        return to_route('admin.users.index')->with('status', 'User updated successfully.');
    }
}
