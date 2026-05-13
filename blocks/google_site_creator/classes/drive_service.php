<?php
// This file is part of Moodle - http://moodle.org/

namespace block_google_site_creator;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Drive API service (copy file, manage folder, set permissions).
 * Uses service account JWT; no Composer dependency.
 *
 * @package   block_google_site_creator
 */
class drive_service {

    /** @var array Service account JSON (decoded) */
    protected $credentials;

    /** @var string|null Cached access token */
    protected $accesstoken = null;

    /** @var int|null Token expiry */
    protected $tokenexpiry = null;

    /** @var string|null Delegated Workspace user for domain-wide delegation */
    protected $delegateduser = null;

    /** Drive API base URL */
    const DRIVE_API = 'https://www.googleapis.com/drive/v3';

    /** OAuth2 token endpoint */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Drive scope (full) */
    const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

    public function __construct(array $credentials, ?string $delegateduser = null) {
        $this->credentials = $credentials;
        $this->delegateduser = $delegateduser;
    }

    /**
     * Get an OAuth2 access token using service account JWT.
     *
     * @return string
     * @throws \moodle_exception
     */
    protected function get_access_token(): string {
        if ($this->accesstoken !== null && $this->tokenexpiry !== null && time() < $this->tokenexpiry - 60) {
            return $this->accesstoken;
        }

        $now = time();
        $payload = [
            'iss' => $this->credentials['client_email'] ?? null,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => self::SCOPE_DRIVE,
        ];
        $subject = trim((string)($this->delegateduser ?? ''));
        if ($subject !== '') {
            $payload['sub'] = $subject;
        }
        if (empty($payload['iss'])) {
            throw new \moodle_exception('error', 'block_google_site_creator', '', null, 'Service account JSON missing client_email');
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64url_encode(json_encode($header)),
            $this->base64url_encode(json_encode($payload)),
        ];
        $signing = implode('.', $segments);

        $key = $this->credentials['private_key'] ?? null;
        if (empty($key)) {
            throw new \moodle_exception('error', 'block_google_site_creator', '', null, 'Service account JSON missing private_key');
        }
        $key = str_replace(["\r\n", "\n", "\r"], "\n", $key);
        $pkey = openssl_pkey_get_private($key);
        if ($pkey === false) {
            throw new \moodle_exception('error', 'block_google_site_creator', '', null, 'Invalid service account private key');
        }
        $sig = '';
        openssl_sign($signing, $sig, $pkey, OPENSSL_ALGO_SHA256);
        $segments[] = $this->base64url_encode($sig);
        $jwt = implode('.', $segments);

        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], '', '&', PHP_QUERY_RFC3986);

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $response = $curl->post(self::TOKEN_URL, $body);
        $code = $curl->get_info()['http_code'] ?? 0;
        if ($code !== 200) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator', '', null, $response);
        }
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }
        $this->accesstoken = $data['access_token'];
        $this->tokenexpiry = $now + (int)($data['expires_in'] ?? 3600);
        return $this->accesstoken;
    }

    private function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Make an authenticated request to Drive API.
     *
     * @param string $method GET|POST|PATCH|DELETE
     * @param string $endpoint e.g. /drive/v3/files (path only)
     * @param array|null $body JSON body
     * @return array Decoded JSON
     * @throws \moodle_exception
     */
    protected function request(string $method, string $endpoint, ?array $body = null): array {
        $url = $endpoint;
        if (strpos($url, 'http') !== 0) {
            $url = self::DRIVE_API . (strpos($endpoint, '/') === 0 ? $endpoint : '/' . $endpoint);
        }
        $token = $this->get_access_token();
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        if ($method === 'GET') {
            $response = $curl->get($url);
        } elseif ($method === 'POST') {
            $response = $curl->post($url, $body !== null ? json_encode($body) : '{}');
        } elseif ($method === 'PATCH') {
            $response = $curl->patch($url, $body !== null ? json_encode($body) : '{}');
        } else {
            $response = $curl->delete($url);
        }
        $code = $curl->get_info()['http_code'] ?? 0;
        if ($code < 200 || $code >= 300) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator', '', null, $response);
        }
        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : [];
    }

    /**
     * Copy a Drive file (template) with a new name and optional parent folder.
     *
     * @param string $fileId Template file ID
     * @param string $newName Title for the copy
     * @param string|null $parentId Optional folder ID to place the copy in
     * @return array With keys id, name, webViewLink (or webContentLink)
     * @throws \moodle_exception
     */
    public function copy_file(string $fileId, string $newName, ?string $parentId = null): array {
        $body = ['name' => $newName];
        if ($parentId !== null && $parentId !== '') {
            $body['parents'] = [$parentId];
        }
        $result = $this->request('POST', '/files/' . $fileId . '/copy?supportsAllDrives=true&fields=id,name,webViewLink,webContentLink', $body);
        return $result;
    }

    /**
     * Update metadata on a Drive file.
     *
     * @param string $fileid
     * @param array $metadata
     * @return array
     */
    public function update_file_metadata(string $fileid, array $metadata): array {
        return $this->request(
            'PATCH',
            '/files/' . $fileid . '?supportsAllDrives=true&fields=id,name,description,webViewLink,webContentLink',
            $metadata
        );
    }

    /**
     * Find or create a folder in the service account's Drive (or shared Drive) by name.
     * Searches for a folder with the given name in the root; if not found, creates it.
     * Note: Service account has its "own" Drive; if you need user's Drive, use domain-wide delegation and impersonate.
     *
     * @param string $folderName Course short name
     * @param string|null $parentId Parent folder ID (null = root)
     * @return string Folder ID
     * @throws \moodle_exception
     */
    public function ensure_folder_by_name(string $folderName, ?string $parentId = null): string {
        $q = "name = '" . str_replace("'", "\\'", $folderName) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        if ($parentId) {
            $q .= " and '" . str_replace("'", "\\'", $parentId) . "' in parents";
        } else {
            $q .= " and 'root' in parents";
        }
        $list = $this->request(
            'GET',
            '/files?q=' . urlencode($q) . '&fields=files(id,name)&pageSize=1&supportsAllDrives=true&includeItemsFromAllDrives=true'
        );
        if (!empty($list['files'][0]['id'])) {
            return $list['files'][0]['id'];
        }
        $body = [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];
        if ($parentId) {
            $body['parents'] = [$parentId];
        }
        $created = $this->request('POST', '/files?fields=id&supportsAllDrives=true', $body);
        if (empty($created['id'])) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }
        return $created['id'];
    }

    /**
     * Add a permission to a file (share with user/group). No notification email.
     *
     * @param string $fileId Drive file ID
     * @param string $emailAddress Email to share with
     * @param string $role reader|writer|commenter
     * @return void
     * @throws \moodle_exception
     */
    public function add_permission(
        string $fileId,
        string $emailAddress,
        string $role = 'writer',
        string $type = 'user',
        array $queryparams = [],
        bool $sendnotificationemail = false
    ): void {
        $query = array_merge(['fields' => 'id', 'supportsAllDrives' => 'true'], $queryparams);
        $body = [
            'type' => $type,
            'role' => $role,
            'emailAddress' => $emailAddress,
            'sendNotificationEmail' => $sendnotificationemail,
        ];
        $this->request('POST', '/files/' . $fileId . '/permissions?' . http_build_query($query), $body);
    }

    /**
     * List permissions for a Drive file.
     *
     * @param string $fileid
     * @return array
     */
    public function list_permissions(string $fileid): array {
        $response = $this->request(
            'GET',
            '/files/' . $fileid . '/permissions?supportsAllDrives=true&fields=permissions(id,type,emailAddress,role)'
        );
        return $response['permissions'] ?? [];
    }

    /**
     * Delete a permission by id.
     *
     * @param string $fileid
     * @param string $permissionid
     * @return void
     */
    public function delete_permission(string $fileid, string $permissionid): void {
        $this->request('DELETE', '/files/' . $fileid . '/permissions/' . $permissionid . '?supportsAllDrives=true');
    }

    /**
     * Transfer ownership of a Drive file/folder to another user in the same Workspace domain.
     *
     * @param string $fileId
     * @param string $newowneremail
     * @return void
     */
    public function transfer_ownership(string $fileId, string $newowneremail): void {
        $this->add_permission(
            $fileId,
            $newowneremail,
            'owner',
            'user',
            ['transferOwnership' => 'true'],
            true
        );
    }
}
