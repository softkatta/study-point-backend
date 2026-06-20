<?php

namespace App\Services;

use App\Support\BiometricDefaults;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SmartOffice 1.0.3 Web API client.
 *
 * @see SmartOfficeAPIDocumentation.pdf
 */
class SmartOfficeService
{
    public function config(): array
    {
        return BiometricDefaults::merge(Setting::getSection('biometric'));
    }

    public function isActive(): bool
    {
        $config = $this->config();

        return ($config['provider'] ?? '') === 'smartoffice'
            && ($config['enabled'] ?? false)
            && filled($config['smartoffice_api_key'] ?? null)
            && filled($config['smartoffice_server_url'] ?? null);
    }

    /** POST /api/v2/WebAPI/AddBiometric */
    public function addDevice(string $deviceName, string $serialNumber): array
    {
        return $this->decode($this->postV2('AddBiometric', [
            'DeviceName' => $deviceName,
            'SerialNumber' => $serialNumber,
        ]));
    }

    /** GET /api/v2/WebAPI/DeleteBiometric */
    public function deleteDevice(string $serialNumber): array
    {
        return $this->decode($this->getV2('DeleteBiometric', [
            'SerialNumber' => $serialNumber,
        ]));
    }

    /** GET /api/v2/WebAPI/GetDeviceLogs */
    public function getDeviceLogs(string $fromDate, string $toDate): array
    {
        $data = $this->decode($this->getV2('GetDeviceLogs', [
            'FromDate' => $fromDate,
            'ToDate' => $toDate,
        ]));

        return is_array($data) && array_is_list($data) ? $data : [];
    }

    /** POST /api/v2/WebAPI/UploadUser */
    public function uploadUser(array $payload): array
    {
        return $this->decode($this->postV2('UploadUser', [
            'EmployeeName' => $payload['employee_name'],
            'EmployeeCode' => $payload['employee_code'],
            'CardNumber' => $payload['card_number'] ?? $payload['employee_code'],
            'SerialNumber' => $payload['serial_number'],
            'IsFaceUpload' => (bool) ($payload['is_face_upload'] ?? false),
            'IsFPUpload' => (bool) ($payload['is_fp_upload'] ?? true),
            'IsCardUpload' => (bool) ($payload['is_card_upload'] ?? false),
            'IsBioPasswordUpload' => (bool) ($payload['is_bio_password_upload'] ?? false),
        ]));
    }

    /** POST /api/v2/WebAPI/DeleteUser */
    public function deleteUser(string $employeeCode, string $serialNumber): array
    {
        return $this->decode($this->postV2('DeleteUser', [
            'EmployeeCode' => $employeeCode,
            'SerialNumber' => $serialNumber,
        ]));
    }

    /** GET /api/v2/WebAPI/FetchLiveUsersFromBiometric */
    public function fetchLiveUsers(string $serialNumber): array
    {
        $data = $this->decode($this->getV2('FetchLiveUsersFromBiometric', [
            'SerialNumber' => $serialNumber,
        ]));

        return is_array($data) && array_is_list($data) ? $data : [];
    }

    /** GET /api/v2/WebAPI/SetUserExpiration */
    public function setUserExpiration(string $employeeCode, string $expirationDate, string $serialNumber = '0'): array
    {
        return $this->decode($this->getV2('SetUserExpiration', [
            'SerialNumber' => $serialNumber,
            'EmployeeCode' => $employeeCode,
            'ExpirationDate' => $expirationDate,
        ]));
    }

    /** GET /api/WebAPI/GetDeviceCommands */
    public function getDeviceCommands(string $fromDate, string $toDate, ?string $serialNumbers = null): array
    {
        $params = [
            'FromDate' => $fromDate,
            'ToDate' => $toDate,
        ];
        if ($serialNumbers) {
            $params['SerialNumbers'] = $serialNumbers;
        }

        return $this->decode($this->getV1('GetDeviceCommands', $params));
    }

    /** POST /api/WebAPI/PhotoUploadInBiometric */
    public function photoUpload(string $serialNumber, string $employeeName, string $employeeCode, string $base64String): array
    {
        return $this->decode($this->postV1('PhotoUploadInBiometric', [
            'SerialNumber' => $serialNumber,
            'EmployeeName' => $employeeName,
            'EmployeeCode' => $employeeCode,
            'Base64String' => $base64String,
        ]));
    }

    /** GET /api/WebAPI/BlockUserinBiometric — BlockUser: 0=block, 1=unblock */
    public function blockUser(string $employeeCode, string $serialNumber, bool $unblock = false): array
    {
        return $this->decode($this->getV1('BlockUserinBiometric', [
            'EmployeeCode' => $employeeCode,
            'SerialNumber' => $serialNumber,
            'BlockUser' => $unblock ? '1' : '0',
        ]));
    }

    /** GET /api/v2/WebAPI/AddEmployee */
    public function addEmployee(array $staff): array
    {
        $body = [
            'StaffCode' => $staff['code'],
            'StaffName' => $staff['name'],
            'Gender' => $staff['gender'] ?? 'Male',
            'Status' => $staff['status'] ?? 'Working',
            'CompanySName' => $staff['company'] ?? 'StudyPoint',
            'DepartmentSName' => $staff['department'] ?? 'Students',
            'Location' => $staff['location'] ?? 'Default',
            'Designation' => $staff['designation'] ?? 'Student',
            'Grade' => $staff['grade'] ?? 'Default',
            'Team' => $staff['team'] ?? 'Default',
            'DOJ' => $staff['doj'] ?? now()->format('Y-m-d'),
            'DOC' => $staff['doc'] ?? now()->format('Y-m-d'),
            'DOB' => $staff['dob'] ?? '1990-01-01',
            'DOR' => $staff['dor'] ?? '3000-01-01',
        ];

        return $this->decode($this->getV2('AddEmployee', $body));
    }

    /** GET /api/v2/WebAPI/DeleteEmployee */
    public function deleteEmployee(string $employeeCode): array
    {
        return $this->decode($this->getV2('DeleteEmployee', [
            'EmployeeCode' => $employeeCode,
        ]));
    }

    /** GET /api/v2/WebAPI/TriggerUserOnlineEnrollment */
    public function triggerOnlineEnrollment(string $serialNumber, string $employeeCode, string $employeeName, int $backupNumber = 1): array
    {
        return $this->decode($this->getV2('TriggerUserOnlineEnrollment', [
            'SerialNumber' => $serialNumber,
            'EmployeeCode' => $employeeCode,
            'EmployeeName' => $employeeName,
            'backup_number' => (string) $backupNumber,
        ]));
    }

    public function testConnection(): array
    {
        $from = now()->subDays(7)->format('Y-m-d H:i');
        $to = now()->format('Y-m-d H:i');
        $result = $this->getDeviceCommands($from, $to);

        if (($result['result'] ?? true) === false || ($result['status'] ?? '') === 'failure') {
            throw new RuntimeException((string) ($result['message'] ?? 'SmartOffice connection test failed.'));
        }

        return ['ok' => true, 'provider' => 'smartoffice'];
    }

    public static function employeeCode(string $studentCode): string
    {
        $code = preg_replace('/^SP/i', '', $studentCode);

        return $code !== '' ? $code : $studentCode;
    }

    private function baseUrl(): string
    {
        $base = rtrim((string) ($this->config()['smartoffice_server_url'] ?? ''), '/');
        if ($base === '') {
            throw new RuntimeException('SmartOffice server URL is required.');
        }

        return $base;
    }

    private function apiKey(): string
    {
        $key = (string) ($this->config()['smartoffice_api_key'] ?? '');
        if ($key === '') {
            throw new RuntimeException('SmartOffice API key is required.');
        }

        return $key;
    }

    private function getV2(string $path, array $params = []): Response
    {
        return $this->requestV2($path, $params, 'GET');
    }

    private function postV2(string $path, array $body = []): Response
    {
        return $this->requestV2($path, $body, 'POST');
    }

    private function getV1(string $path, array $params = []): Response
    {
        return $this->requestV1($path, $params, 'GET');
    }

    private function postV1(string $path, array $body = []): Response
    {
        return $this->requestV1($path, $body, 'POST');
    }

    private function requestV2(string $path, array $payload, string $method): Response
    {
        return $this->send("{$this->baseUrl()}/api/v2/WebAPI/{$path}", $payload, $method);
    }

    private function requestV1(string $path, array $payload, string $method): Response
    {
        return $this->send("{$this->baseUrl()}/api/WebAPI/{$path}", $payload, $method);
    }

    private function send(string $url, array $payload, string $method): Response
    {
        $payload['APIKey'] = $this->apiKey();
        $request = Http::timeout(20)->acceptJson();

        $response = match (strtoupper($method)) {
            'POST' => $request->post($url, $payload),
            'GET' => $request->get($url, $payload),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };

        if (! $response->successful()) {
            throw new RuntimeException('SmartOffice API error: '.$this->httpError($response));
        }

        return $response;
    }

    private function decode(Response $response): array
    {
        $body = $response->json();

        if (is_array($body) && array_is_list($body)) {
            return $body;
        }

        if (! is_array($body)) {
            throw new RuntimeException('Unexpected SmartOffice response.');
        }

        if (($body['status'] ?? null) === false || ($body['Result'] ?? null) === false) {
            throw new RuntimeException((string) ($body['message'] ?? $body['Message'] ?? 'SmartOffice request failed.'));
        }

        if (($body['result'] ?? null) === false || ($body['status'] ?? null) === 'failure') {
            throw new RuntimeException((string) ($body['message'] ?? $body['Message'] ?? 'SmartOffice request failed.'));
        }

        return $body;
    }

    private function httpError(Response $response): string
    {
        $body = $response->json();
        if (is_array($body)) {
            return (string) ($body['message'] ?? $body['Message'] ?? $body['error'] ?? $response->body());
        }

        return (string) $response->body();
    }
}
