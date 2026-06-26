<?php
// VibeNest MySQL console — Adminer with env-driven autologin.
//
// The platform injects DB_HOST / DB_PORT / DB_USER / DB_PASSWORD / DB_NAME (parsed from the
// managed database's InternalUrl). We override the Adminer class so it connects straight to
// that one database with no login screen: credentials()/database() supply the connection,
// login() trusts the env creds, permanentLogin() gives a stable session key, and loginForm()
// auto-submits a CSRF-valid login form on the first request (so the very first visit also
// lands inside the DB instead of on the form).
//
// HTTP basic auth is enforced one layer up by Caddy (AUTH_USER/AUTH_PASS) — that is the real
// access boundary; this file assumes the request already passed it.

function adminer_object()
{
    class VibeNestAdminer extends Adminer
    {
        function name()
        {
            return 'VibeNest MySQL';
        }

        private function server()
        {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            return $host . ':' . $port;
        }

        function credentials()
        {
            return array($this->server(), getenv('DB_USER'), getenv('DB_PASSWORD'));
        }

        function database()
        {
            return getenv('DB_NAME') ?: null;
        }

        function databases($flush = true)
        {
            // Lock the DB list to the one managed database — the console never browses siblings.
            $db = getenv('DB_NAME');
            return $db ? array($db) : parent::databases($flush);
        }

        function login($login, $password)
        {
            // Trust the platform-injected creds (Caddy basic auth already gated the request).
            return true;
        }

        function permanentLogin($create = false)
        {
            // Stable key so the session persists across requests without re-prompting.
            return getenv('AUTH_PASS') ?: 'vibenest-mysql-console';
        }

        function loginForm()
        {
            // Render Adminer's OWN login fields (they carry a valid CSRF token), then a script that
            // fills that form and submits it — so the first visit lands straight inside the database.
            // We must NOT emit our own <form> here: this method runs INSIDE Adminer's <form>, and
            // browsers silently drop nested forms (the original bug — the auto-submit form vanished
            // from the DOM). A sessionStorage once-guard prevents an infinite resubmit loop if the
            // credentials are ever rejected.
            parent::loginForm();
            $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
            $server = json_encode($this->server(), $flags);
            $user = json_encode(getenv('DB_USER') ?: '', $flags);
            $pass = json_encode(getenv('DB_PASSWORD') ?: '', $flags);
            $db = json_encode(getenv('DB_NAME') ?: '', $flags);
            echo "<noscript><p>Enable JavaScript to open the database console.</p></noscript>\n";
            echo "<script>\n";
            echo "(function(){\n";
            echo "  if (sessionStorage.getItem('vbn_autologin')) return;\n";
            echo "  var f = document.querySelector(\"form[action='']\") || document.forms[0];\n";
            echo "  if (!f || !f.elements || !f.elements['auth[password]']) return;\n";
            echo "  function set(n,v){ var e=f.elements[n]; if(e!=null) e.value=v; }\n";
            echo "  set('auth[driver]','server');\n";
            echo "  set('auth[server]',$server);\n";
            echo "  set('auth[username]',$user);\n";
            echo "  set('auth[password]',$pass);\n";
            echo "  set('auth[db]',$db);\n";
            echo "  sessionStorage.setItem('vbn_autologin','1');\n";
            echo "  f.submit();\n";
            echo "})();\n";
            echo "</script>\n";
        }
    }

    return new VibeNestAdminer;
}

include './adminer.php';
