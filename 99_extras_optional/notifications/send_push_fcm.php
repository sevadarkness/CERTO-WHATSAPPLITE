<?php
function sendNotificationToVendedor($vendedor_id, $cliente_nome, $cliente_numero) {
    // Simulando busca do token Firebase por ID
    $tokens = [
        'luizhenrique' => 'FAKE_FIREBASE_DEVICE_TOKEN_123456'
    ];

    $token = $tokens[$vendedor_id] ?? null;
    if (!$token) return;

    $title = "📩 Nova oportunidade de venda";
    $body = "O cliente $cliente_nome ($cliente_numero) recebeu uma mensagem sua.";
    $link = "https://painel.redealabama.com/painel_vendedor.php?id=$vendedor_id&cliente=" . urlencode($cliente_nome);

    $message = [
        "to" => $token,
        "notification" => [
            "title" => $title,
            "body" => $body,
            "click_action" => $link
        ]
    ];

    $ch = curl_init("https://fcm.googleapis.com/fcm/send");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: key=YOUR_FIREBASE_SERVER_KEY",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    $result = curl_exec($ch);
    curl_close($ch);
}
?>
