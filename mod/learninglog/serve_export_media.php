<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$token = optional_param('token', '', PARAM_ALPHANUMEXT);
$expiry = optional_param('expiry', '', PARAM_INT);
$f = optional_param('f', '', PARAM_RAW);

if ($token === '' || $expiry === '' || $f === '') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if ((int) $expiry < time()) {
    header('HTTP/1.1 410 Gone');
    header('X-Moodle-Export-Media-Reason: expired');
    exit;
}

$b64 = strtr($f, '-_', '+/');
$payload = base64_decode($b64 . str_repeat('=', (4 - strlen($b64) % 4) % 4), true);
if ($payload === false) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$data = json_decode($payload, true);
if (!$data || empty($data['c']) || empty($data['co']) || empty($data['a']) || !isset($data['i']) ||
    $data['co'] !== 'mod_learninglog' || !in_array($data['a'], ['banner', 'embedded'], true)) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$secret = learninglog_get_export_media_secret();
$expected = hash_hmac('sha256', (string) $expiry . $f, $secret);
if (!hash_equals($expected, $token)) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$fs = get_file_storage();
$file = $fs->get_file(
    (int) $data['c'],
    $data['co'],
    $data['a'],
    (int) $data['i'],
    isset($data['p']) ? $data['p'] : '/',
    isset($data['n']) ? $data['n'] : ''
);

if (!$file || $file->is_directory()) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

send_stored_file($file, 0, 0, false, []);
