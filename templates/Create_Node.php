<?php

// Create_Node.php

session_start();

/* -------------------------------------------------
   Security gate
------------------------------------------------- */
if (
    empty($_SESSION['install_verified']) ||
    ($_SESSION['install_verified_until'] ?? 0) < time()
) {
?>

<div class="w3-warning w3-card w3-padding w3-margin-top">

    <h1 class="w3-large">Verification Required</h1>

    <div>
        You must verify your email address before creating a node.
    </div>

    <div>
        <a class="w3-button w3-black" href="/#get-forum">
            Verify Email
        </a>
    </div>

</div>

<?php
    exit;
}
?>

<div class="w3-light-grey w3-padding w3-auto w3-card w3-round-large w3-margin-top">



    <h1 class="w3-bold w3-large">CREATE FORUM</h1>

    <div class="w3-margin-top">
        You are verified as:
        <?php
        $email = htmlspecialchars($_SESSION['install_email'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>
        <b><?=$email?></b>
    </div>

    <form method="post" action="/?p=Install_Node">
    
    	<input type="hidden" name="email" value="<?=$email?>">

        <div class="w3-margin-top">
            <label for="forum_dir"><b>Forum name</b></label><br>

            <input
            	placeholder="Your Name Here"
                type="text"
                id="forum_dir"
                name="forum_dir"
                maxlength="50"
                pattern="[a-zA-Z0-9_-]+"
                required>
        </div>

        <div class="w3-margin-top">
            <input class="w3-check" type="checkbox" id="aup" name="aup" value="Yes" required>
            &nbsp;
            <label for="aup">
                I agree to the
                <a target="legal" href="/?p=Legal#aup">Acceptable Use Policy</a>
                and
                <a target="legal" href="/?p=Legal#terms">Terms of Service</a>.
            </label>
        </div>

        <div class="w3-margin-top">
            <button class="w3-button w3-black" type="submit">
                Submit
            </button>
        </div>
        
    </form>
    
</div>
