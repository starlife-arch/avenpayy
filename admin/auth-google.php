<?php
require_once __DIR__ . '/../database_config/env.php';
session_start();
require_once __DIR__ . '/../database_config/db.php';

$client_id     = av_env('GOOGLE_CLIENT_ID');
$client_secret = av_env('GOOGLE_CLIENT_SECRET');
$redirect_uri  = rtrim(av_env('APP_URL', 'https://avenpay.co.ke'), '/') . '/admin/auth-google';

if (!isset($_GET['code'])) {
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'email profile',
        'prompt'        => 'select_account'
    ]);
    header("Location: " . $auth_url);
    exit();
} else {
    if (isset($_GET['error'])) {
        $_SESSION['error'] = "Sign-in cancelled.";
        header("Location: /admin/");
        exit();
    }

    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code'          => $_GET['code'],
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_info_response = curl_exec($ch);
        curl_close($ch);

        $google_user = json_decode($user_info_response, true);
        $email = $google_user['email'] ?? '';

        if (!empty($email)) {
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
                    header("Location: /admin/");
                    exit();
                }
                $stmt->close();
            }
        }
    }

    $_SESSION['error'] = "Authentication failed. Please try again.";
    header("Location: /admin/");
    exit();
}