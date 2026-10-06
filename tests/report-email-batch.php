<?php
namespace ReportEmailFixture;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Exercise the actual gateway code with in-memory cURL, never real recipients.
$calls = [];
function curl_init($url) { return (object)['url' => $url, 'options' => []]; }
function curl_setopt($handle, $option, $value) { $handle->options[$option] = $value; return true; }
function curl_exec($handle) { $GLOBALS['calls'][] = $handle; return '{"messageId":"fixture","id":"fixture"}'; }
function curl_getinfo($handle, $option) { return strpos($handle->url, 'brevo') !== false ? 201 : 200; }
function curl_error($handle) { return ''; }
function curl_close($handle) {}
function check($value, $message) { if (!$value) throw new \RuntimeException($message); }
$source = file_get_contents(__DIR__ . '/../helpers/SettingsHelper.php');
$start = strpos($source, 'class SettingsHelper');
$end = strpos($source, '// INITIALIZE DEFAULTS ON FIRST RUN');
eval('namespace ReportEmailFixture; use \\PDO; use \\Throwable; use \\Exception; ' . substr($source, $start, $end - $start));
$settings = new \ReflectionProperty(SettingsHelper::class, 'settings');
$settings->setAccessible(true);
$settings->setValue(null, ['enable_email_receipts'=>1,'email_gateway'=>'brevo','brevo_api_key'=>'fixture-key','brevo_sender_email'=>'sender@example.test']);
$recipients = [['email'=>'one@example.test','name'=>'One'],['email'=>'two@example.test','name'=>'Two'],['email'=>'ONE@example.test','name'=>'One'],['email'=>'invalid','name'=>'Invalid']];
check(SettingsHelper::sendReportEmailMany($recipients, 'Fixture', '<p>Fixture notice</p>'), 'Brevo batch accepted');
check(count($calls) === 1, 'One network call for the officials, with duplicates excluded');
$payload = json_decode($calls[0]->options[CURLOPT_POSTFIELDS], true);
check(!isset($payload['to']) && count($payload['messageVersions']) === 2, 'Brevo sends private versions');
foreach ($payload['messageVersions'] as $version) check(count($version['to']) === 1, 'Each message exposes only its recipient');
check($calls[0]->options[CURLOPT_TIMEOUT] === 10, 'Interactive network timeout is bounded');
check(SettingsHelper::sendEmail('single@example.test', '', 'Single', '<p>Fixture</p>', ['gateway'=>'brevo','api_key'=>'fixture-key','sender_email'=>'sender@example.test']), 'Single-recipient delivery remains supported');
$single = json_decode($calls[1]->options[CURLOPT_POSTFIELDS], true);
check(count($single['to']) === 1 && $single['to'][0]['name'] === 'single@example.test', 'Missing display name falls back to email');
check(SettingsHelper::sendEmail(array_slice($recipients,0,2), '', 'Mailgun', '<p>Fixture</p>', ['gateway'=>'mailgun','api_key'=>'fixture-key','domain'=>'example.test','sender_email'=>'sender@example.test']), 'Mailgun batch accepted');
parse_str($calls[2]->options[CURLOPT_POSTFIELDS], $mailgun);
check(count(json_decode($mailgun['recipient-variables'],true)) === 2, 'Mailgun uses private batch recipient variables');
check(!SettingsHelper::sendReportEmailMany([['email'=>'invalid']], 'Invalid', 'Fixture'), 'Invalid-only batch does not send');
check(count($calls) === 3, 'Invalid recipients do not add network calls');
echo "Private batch delivery, deduplication, single emails, and gateway timeouts passed.\n";
