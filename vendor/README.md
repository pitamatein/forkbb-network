If you replace forkbb-master with a more recent ForkBB revision, search forkbb-master/app/Models/Pages/Admin/Install.php for 'o_smtp" and change the following:

```
'o_webmaster_email'       => \getenv('FORKBB_WEBMASTER_EMAIL') ?: '',

'o_smtp_host'             => \getenv('FORKBB_SMTP_HOST') ?: '',
'o_smtp_user'             => \getenv('FORKBB_SMTP_USER') ?: '',
'o_smtp_pass'             => \getenv('FORKBB_SMTP_PASS') ?: '',
```
