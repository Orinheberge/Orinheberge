<?php
/**
 * OrinHeberge — Client OAuth générique
 * Supporte Discord, Google, et tout provider OAuth2 standard
 */

class OAuthProvider {
    private $name;
    private $clientId;
    private $clientSecret;
    private $authorizeUrl;
    private $tokenUrl;
    private $userInfoUrl;
    private $scopes;
    private $redirectUri;

    public function __construct($name, $config) {
        $this->name         = $name;
        $this->clientId     = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->authorizeUrl = $config['authorize_url'];
        $this->tokenUrl     = $config['token_url'];
        $this->userInfoUrl  = $config['user_info_url'];
        $this->scopes       = $config['scopes'] ?? [];
        $this->redirectUri  = $config['redirect_uri'];
    }

    /**
     * Génère l'URL d'autorisation et stocke le state en session
     */
    public function getAuthorizationUrl() {
        $state = bin2hex(random_bytes(16));
        $_SESSION["oauth_{$this->name}_state"] = $state;

        $params = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->scopes),
            'state'         => $state,
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    /**
     * Échange le code contre un token et récupère les infos user
     */
    public function handleCallback($code, $state) {
        // Vérification du state (protection CSRF)
        $expectedState = $_SESSION["oauth_{$this->name}_state"] ?? null;
        if (!$expectedState || !hash_equals($expectedState, $state)) {
            throw new Exception("État OAuth invalide (attaque CSRF possible)");
        }
        unset($_SESSION["oauth_{$this->name}_state"]);

        // Échanger code -> access token
        $accessToken = $this->exchangeCode($code);

        // Récupérer infos user
        $userData = $this->fetchUserInfo($accessToken);

        return $this->normalizeUserData($userData);
    }

    private function exchangeCode($code) {
        $ch = curl_init($this->tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri,
            ]),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur échange code OAuth (HTTP $httpCode)");
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private function fetchUserInfo($accessToken) {
        $ch = curl_init($this->userInfoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $accessToken",
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur récupération user OAuth (HTTP $httpCode)");
        }

        return json_decode($response, true);
    }

    /**
     * Normalise les données selon le provider
     */
    private function normalizeUserData($data) {
        switch ($this->name) {
            case 'discord':
                $avatar = $data['avatar'] 
                    ? "https://cdn.discordapp.com/avatars/{$data['id']}/{$data['avatar']}.png?size=128"
                    : null;
                return [
                    'id'        => $data['id'],
                    'email'     => $data['email'] ?? null,
                    'username'  => $data['username'] ?? null,
                    'firstname' => $data['global_name'] ?? $data['username'] ?? 'User',
                    'lastname'  => '',
                    'avatar'    => $avatar,
                    'raw'       => $data,
                ];

            case 'google':
                return [
                    'id'        => $data['sub'],
                    'email'     => $data['email'],
                    'username'  => $data['email'],
                    'firstname' => $data['given_name'] ?? 'User',
                    'lastname'  => $data['family_name'] ?? '',
                    'avatar'    => $data['picture'] ?? null,
                    'raw'       => $data,
                ];

            default:
                throw new Exception("Provider OAuth inconnu : {$this->name}");
        }
    }
}