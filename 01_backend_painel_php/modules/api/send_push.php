<?php
// Envia notificação push via Firebase Cloud Messaging
function enviarNotificacao($titulo, $mensagem, $token) {
    $url = 'https://fcm.googleapis.com/fcm/send';
    $chaveServidor = 'FIREBASE_SERVER_KEY'; // substitua pelo seu Server Key
    $payload = [
        'to' => $token,
        'notification' => [
            'title' => $titulo,
            'body' => $mensagem,
            'click_action' => 'https://painel.redealabama.com'
        ]
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: key=' . $chaveServidor,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $resposta = curl_exec($ch);
    curl_close($ch);
    return $resposta;
}
echo enviarNotificacao("📢 Nova campanha publicada!", "Confira no painel agora!", $_GET['token']);
?>
