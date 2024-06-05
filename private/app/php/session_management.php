<?php

    include_once LIBRARY_PATH . '/auth.php';

    $session_timeout = 1440;
    $remaining_time = $session_timeout - (time() - $_SESSION['last_activity']);


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_ACTION'])) {
        // CSRF Protection
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
    
            $csrf_token = generate_csrf_token();
            $respond = array(
                'success' => false,
                'csrf_token' => $csrf_token
            );
            echo json_encode($respond);
            exit;
        }



        if($_SERVER['HTTP_ACTION'] === 'getSessionTime'){

            $remaining_time = $session_timeout - (time() - $_SESSION['last_activity']);

            error_log( $session_timeout);
            error_log( time() . "   " . $_SESSION['last_activity']);
            error_log( $remaining_time);

            $csrf_token = generate_csrf_token();
            $respond = array(
                'success' => true,
                'csrf_token' => $csrf_token,
                'remainingTime' => $remaining_time
            );
            echo json_encode($respond);
            exit;
        }




        if($_SERVER['HTTP_ACTION'] === 'resetTimer'){
            $_SESSION['last_activity'] = time();
            $remaining_time = $session_timeout - (time() - $_SESSION['last_activity']);

            error_log( $session_timeout);
            error_log( time() . "   " . $_SESSION['last_activity']);
            error_log( $remaining_time);

            $csrf_token = generate_csrf_token();
            $respond = array(
                'success' => true,
                'csrf_token' => $csrf_token,
                'remainingTime' => $remaining_time
            );
            echo json_encode($respond);
            exit;
        }




        if($_SERVER['HTTP_ACTION'] === 'getLoginPanel'){
            if (file_exists(ENV_FILE_PATH)){
                $env = parse_ini_file(ENV_FILE_PATH);
            }
            $login_available = false;
            if ((isset($env) ? $env["Authentication"] : getenv("Authentication")) == "OIDC") {
                // Open ID Connect
                $login_available = true;
                $form = 
                '<div class="modal-panel">
                    <div class="modal-content">
                        <h3>' . $translation['sessionTimeout_Msg'] . '</h3>

                        <form action="/oidc_login" class="column" method="post" id="loginForm">

                        </form>
                        <div id="login-Button-panel">
                            <button type="submit" name="submit">' . $translation['Login'] . '</button >
                            <button type="button" onclick="Logout()">' . $translation['SignOut'] .'</button>
                        </div>
                        <div id="login-message"></div>

                    </div>
                </div>';
            }
            if ((isset($env) ? $env["Authentication"] : getenv("Authentication")) == "LDAP") {
                $login_available = true;
                $form = 
                    '<div class="modal-panel">
                        <div class="modal-content">
                            <h3>' . $translation['sessionTimeout_Msg'] . '</h3>
                            <form class="column" id="loginForm">
                                <label for="account">' . $translation["username"] . '</label>
                                <input type="text" name="account" id="account">
                                <label for="password">' . $translation["password"] . '</label>
                                <input type="password" name="password" id="password">
                            </form>
                            <div id="login-Button-panel">
                                <button class="outlineButton" type="button" onclick="Logout()">' . $translation['SignOut'] .'</button>
                                <button id="loginButton" type="button" onclick="LoginLDAP()">' . $translation['Login'] .'</button>
                            </div>
                            <div id="login-message"></div>

                        </div>
                    </div>';
            }
            if ((isset($env) ? $env["Authentication"] : getenv("Authentication")) == "Shibboleth") {
                $login_available = true;
                $form = 
                    '<div class="modal-panel">
                        <div class="modal-content">
                            <h3>' . $translation['sessionTimeout_Msg'] . '</h3>

                            <form class="column" method ="post" id="loginForm">
                                <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                            </form>
                            <div id="login-Button-panel">
                                <button type="submit" name="submit">' . $translation['Login'] . '</button >
                                <button type="button" onclick="Logout()">' . $translation['SignOut'] .'</button>
                            </div>
                            <div id="login-message"></div>

                        </div>
                    </div>';
            }
            if (!$login_available) {
                $csrf_token = generate_csrf_token();
                $respond = array(
                    'success' => true,
                    'csrf_token' => $csrf_token,
                );
                echo json_encode($respond);
                exit;
            }
            
            $csrf_token = generate_csrf_token();
            $respond = array(
                'success' => true,
                'csrf_token' => $csrf_token,
                'loginForm' => $form
            );
            echo json_encode($respond);
            exit;
        }
    }
?>


<script>
    const sessionTimeout = <?php echo $remaining_time; ?>;
    let interval;

    function startSessionTimer(duration) {
        let timer = duration;

        interval = setInterval(function () {
            // console.log('Timer value before decrement:', timer);
        console.log(timer);
            
            if (--timer < 0) {
                console.log('Timer value when clearing interval:', timer);
                clearInterval(interval);
                fetchLoginPanel();  // Fetch login panel when timer expires
            }
        }, 1000);
    }

    function checkRemainingTime() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // REQUEST THE LOGIN FORM CONTENT FROM SERVER
        fetch('/interface', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'action': 'getSessionTime'
            },
        })
        .then(response => response.json())
        .then(data => {
            document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
            if (data.success) {
                //Restart Interface Timer
                clearInterval(interval);
                startSessionTimer(data.remainingTime);
            }
        })
        .catch(error => console.error(error));
    }

    // Start the session timer with the initial timeout value
    startSessionTimer(sessionTimeout);
    // Periodically check for activity updates two seconds before timeout
    setInterval(checkRemainingTime, sessionTimeout - 2000);


    function fetchLoginPanel(){
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // REQUEST THE LOGIN FORM CONTENT FROM SERVER
        fetch('/interface', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'action': 'getLoginPanel'
            },
        })
        .then(response => response.json())
        .then(data => {
            document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
            if(data.success){
                var loginFormContainer = document.createElement('div');
                loginFormContainer.classList.add('modal');
                loginFormContainer.id = "login-modal";
                loginFormContainer.innerHTML = data.loginForm;
                document.body.appendChild(loginFormContainer);
            }
        })
        .catch(error => console.error(error));  
    }


	function LoginLDAP() {
		var formData = new FormData();
		formData.append("account", document.getElementById("account").value);
		formData.append("password", document.getElementById("password").value);
		formData.append("csrf_token", document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

		fetch('/login', {
			method: "POST",
			body: formData
			})
		.then(response => {
			if (!response.ok) {
				throw new Error("Login request failed");
			}
			return response.json();
			})
		.then(data => {
			//Update Header CSRF.
			document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);

			if (data.success) {
                document.getElementById("login-modal").remove();

                resetTimer();

			} else {
				// console.log('wrong credentials');
				document.getElementById("login-message").textContent = data.message; // Display error message

			}
			})
		.catch(error => {
			console.error(error);
		});
	}

    function resetTimer() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        fetch('/interface', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'action': 'resetTimer'
            },
        })
        .then(response => response.json())
        .then(data => {
            document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
            if (data.success) {
                //Restart Interface Timer
                clearInterval(interval);
                startSessionTimer(data.remainingTime);
            }
        })
        .catch(error => console.error(error));
    }

    function Logout(){
        window.location.href = 'logout';
    }

</script>