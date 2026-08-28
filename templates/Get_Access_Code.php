<?php

// Get_Access_Code.php
// Bot gatekeeper. Must use email to request an access code to create a forum.

session_start();

unset($_SESSION['install_verified']);
unset($_SESSION['install_email']);
?>

<div id="get-access-code" class="w3-animate-zoom w3-blue-grey w3-padding w3-auto w3-round-large w3-margin-top">

    <form method="post" action="/?p=Send_Access_Code">

		<div class="w3-row-padding w3-center w3-padding-16">
        	<div class="w3-twothird">
            	<input
            		class="w3-input"
                	placeholder="Enter your email address"
                	type="email"
                	id="email"
                	name="email"
                	maxlength="255"
                	required>
            </div>
            <div class="w3-third">
            	<button
                	class="w3-button w3-black w3-round"
                	type="submit">
                	Yes, I want a <b>FREE</b> forum
            	</button>
            	<a class="w3-button" href="/-/">Examples</a>
        	</div>
        </div>
        
    </form>

</div>
