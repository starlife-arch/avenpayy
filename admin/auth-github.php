<?php
require_once __DIR__ . '/../database_config/env.php';
session_start();
require_once __DIR__ . '/../database_config/db.php';

// TODO: Replace with your actual GitHub Client credentials from GitHub Developer Settings
$client_id     = av_env('GITHUB_CLIENT_ID');
$client_secret = av_env('GITHUB_CLIENT_SECRET');
$redirect_uri  = rtrim(av_env('APP_URL', 'https://avenpay.co.ke'), '/') . '/admin/auth-github';

if (!isset($_GET['code'])) {
    // Step 1: Redirect user to GitHub OAuth login page
    $auth_url = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id'    => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope'        => 'user:email'
    ]);
    header("Location: " . $auth_url);
    exit();
} else {
    // Catch GitHub cancellation or errors
    if (isset($_GET['error'])) {
        $_SESSION['error'] = "Sign-in cancelled.";
        header("Location: index.php");
        exit();
    }

    // Step 2: Exchange authorization code for access token
    $token_url = 'https://github.com/login/oauth/access_token';
    $post_data = [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'code'          => $_GET['code'],
        'redirect_uri'  => $redirect_uri
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        $access_token = $token_data['access_token'];

        // Step 3: Fetch verified user emails from GitHub API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/user/emails');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'User-Agent: AvenPay-Admin-Portal',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $emails_response = curl_exec($ch);
        curl_close($ch);

        $emails = json_decode($emails_response, true);
        $email = '';

        if (is_array($emails)) {
            foreach ($emails as $item) {
                if (!empty($item['primary']) && !empty($item['verified'])) {
                    $email = $item['email'];
                    break;
                }
            }
        }

        if (!empty($email)) {
            // Step 4: Check if email exists in 'admins' table
            $stmt = $conn->prepare("SELECT id, username, email FROM admins WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];

                    header("Location: /admin/dashboard");
                    exit();
                } else {
                    $_SESSION['error'] = "This account is not registered as an administrator.";
                    header("Location: index.php");
                    exit();
                }
                $stmt->close();
            }
        }
    }

    $_SESSION['error'] = "Authentication failed. Please try again.";
    header("Location: index.php");
    exit();
}