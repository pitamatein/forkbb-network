<?php

// Verify_Access_Code.php

session_start();

$payload   = $_GET['d'] ?? '';
$signature = $_GET['s'] ?? '';

$error = null;

if ($payload === '' || $signature === '') {

    $error = 'Invalid verification link.';
}
else {

    $expected = hash_hmac(
        'sha256',
        $payload,
        INSTALL_SECRET
    );

    if (!hash_equals($expected, $signature)) {

        $error = 'Invalid verification link.';
    }
    else {

        $data = json_decode(
            base64_decode($payload),
            true
        );

        if (
            !is_array($data) ||
            empty($data['email'])
        ) {

            $error = 'Invalid verification link.';
        }
        elseif (($data['expires'] ?? 0) < time()) {

            $error = 'Verification link has expired.';
        }
        else {

			$_SESSION['install_verified'] = true;
			$_SESSION['install_email'] = $data['email'];
			$_SESSION['install_verified_until'] = time() + 300;
			
			#error_log('VERIFY SESSION: ' . print_r($_SESSION, true));
			
			header('Location: /?p=Create_Node');
			exit;
        }
    }
}
?>

<div class="w3-warning w3-padding w3-auto w3-card w3-round-large w3-margin-top">

    <p class="w3-bold w3-large">VERIFICATION FAILED</p>

    <p>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <a href="/#get-forum">
            Request a new access code
        </a>
    </p>

</div>
