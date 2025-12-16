<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php'; // Certifique-se de que este arquivo tem a configuração correta para $pdo
require_once __DIR__ . '/menu_navegacao.php';

$usuario    = current_user();
$usuario_id = (int)($usuario['id'] ?? 0);
$query_usuario = "SELECT nome, nivel_acesso FROM usuarios WHERE id = :usuario_id";
$stmt_usuario = $pdo->prepare($query_usuario);
$stmt_usuario->bindParam(':usuario_id', $usuario_id);
$stmt_usuario->execute();
$usuario = $stmt_usuario->fetch();

// Configuração de acesso
$menu_vendedor = ($usuario['nivel_acesso'] === 'Administrador' || $usuario['nivel_acesso'] === 'Gerente');
$acesso_restrito = !$menu_vendedor;

// Filtros de pesquisa
$pesquisa_nome = isset($_POST['pesquisa_nome']) ? $_POST['pesquisa_nome'] : '';
$pesquisa_telefone = isset($_POST['pesquisa_telefone']) ? $_POST['pesquisa_telefone'] : '';

// Consulta SQL para pegar nome, telefone, cliente, data da venda, puffs (capacity), e calcular a data de remarketing
$query_produtos = "
    SELECT 
        p.nome AS nome_produto, 
        v.telefone_cliente, 
        v.nome_cliente, 
        DATE_FORMAT(v.data_venda, '%d/%m/%Y') AS data_venda,
        p.capacity AS capacidade_produto,
        v.data_venda AS data_venda_real,
        v.telefone_cliente,
        COUNT(v.telefone_cliente) OVER(PARTITION BY v.telefone_cliente) AS num_compras
    FROM vendas v
    JOIN produtos p ON v.produto_id = p.id
    WHERE v.telefone_cliente IS NOT NULL
    AND (v.nome_cliente LIKE :pesquisa_nome OR v.telefone_cliente LIKE :pesquisa_telefone)
    ORDER BY v.telefone_cliente, v.data_venda ASC
";

$stmt_produtos = $pdo->prepare($query_produtos);
$stmt_produtos->bindValue(':pesquisa_nome', "%$pesquisa_nome%", PDO::PARAM_STR);
$stmt_produtos->bindValue(':pesquisa_telefone', "%$pesquisa_telefone%", PDO::PARAM_STR);
$stmt_produtos->execute();
$produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

function calcular_remarketing($vendas) {
    if (count($vendas) < 2) {
        return null; // Não tem 2 compras para calcular remarketing
    }

    // Calcular o intervalo entre as compras
    $data_compra_1 = strtotime($vendas[0]['data_venda_real']);
    $data_compra_2 = strtotime($vendas[1]['data_venda_real']);
    $intervalo_dias = ($data_compra_2 - $data_compra_1) / (60 * 60 * 24); // Em dias

    if ($intervalo_dias <= 2) {
        return null; // Não é elegível para remarketing (intervalo menor que 2 dias)
    }

    // Calcular a média de consumo diário (baseado na capacidade do produto)
    $total_capacity = 0;
    $total_dias = 0;
    foreach ($vendas as $venda) {
        $total_capacity += $venda['capacidade_produto'];
        // Considerando a quantidade de dias entre as compras para a média de consumo diário
        $total_dias += $intervalo_dias;
    }

    $media_consumo_diario = $total_capacity / $total_dias;

    // Calcular a previsão de remarketing (próxima compra com base no consumo diário)
    // Exemplo: se o cliente consumiu 5000 puffs em 6 dias, a previsão seria feita com base nesse consumo diário
    $dias_para_remarketing = ceil(5000 / $media_consumo_diario); // Número de dias necessários para o próximo remarketing
    $ultima_compra = $vendas[1]; // Última compra
    $data_remarketing = date("d/m/Y", strtotime($ultima_compra['data_venda_real'] . " + $dias_para_remarketing days"));

    return $data_remarketing;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remarketing - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <style>
        .elegivel {
            font-weight: bold;
        }
        .whatsapp-icon {
            width: 30px;
            height: 30px;
            margin-right: 10px;
        }
        .whatsapp-link {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: green;
        }
        /* Responsividade para telas pequenas */
        @media (max-width: 768px) {
            .whatsapp-icon {
                width: 25px;
                height: 25px;
            }
            .whatsapp-link {
                font-size: 14px;
            }
            .table th, .table td {
                padding: 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h4>Lista de Produtos, Clientes e Dados de Remarketing</h4>

    <form method="POST" class="search-box mb-3">
        <div class="row">
            <div class="col-12 col-md-4 mb-2 mb-md-0">
                <input type="text" name="pesquisa_nome" class="form-control" placeholder="Pesquisar por nome" value="<?php echo htmlspecialchars($pesquisa_nome); ?>">
            </div>
            <div class="col-12 col-md-4 mb-2 mb-md-0">
                <input type="text" name="pesquisa_telefone" class="form-control" placeholder="Pesquisar por telefone" value="<?php echo htmlspecialchars($pesquisa_telefone); ?>">
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-primary btn-block">Filtrar</button>
            </div>
        </div>
    </form>

    <h5>Clientes Elegíveis para Remarketing</h5>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Nome do Produto</th>
                <th>Nome do Cliente</th>
                <th>Telefone do Cliente</th>
                <th>Data da Venda</th>
                <th>Data de Remarketing</th>
                <th>Açao</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $clientes_vendas = [];
            foreach ($produtos as $produto):
                $clientes_vendas[$produto['telefone_cliente']][] = $produto;
            endforeach;

            // Ordenar os clientes por telefone
            ksort($clientes_vendas);

            foreach ($clientes_vendas as $vendas_cliente):
                $data_remarketing = calcular_remarketing($vendas_cliente);
                
                // Só exibe os elegíveis para remarketing
                if ($data_remarketing !== null): 
                    foreach ($vendas_cliente as $produto):
                        $telefone_cliente = $produto['telefone_cliente'];
                        
                        // Formatar o telefone para garantir que tem o código do Brasil sem duplicação
                        $telefone_formatado = preg_replace('/\D/', '', $telefone_cliente);
                        if (substr($telefone_formatado, 0, 2) !== '55') {
                            $telefone_formatado = '55' . $telefone_formatado;
                        }

                        // Link para o WhatsApp com a mensagem formatada corretamente
                        $mensagem_whatsapp = "Seu Pod já está quase no finalzinho, né? Eu sei!%0A%0ANão deixe acabar!%0A%0ARenove hoje mesmo e continue curtindo sem interrupções.%0A%0AEntrega rápida garantida!%0A%0A%Acesse agora mesmo nosso site www.redealabama.com e confira nossas ofertas!";
                        $link_whatsapp = "https://wa.me/$telefone_formatado?text=" . urlencode($mensagem_whatsapp);
            ?>
                        <tr class="elegivel">
                            <td><?php echo htmlspecialchars($produto['nome_produto']); ?></td>
                            <td><?php echo htmlspecialchars($produto['nome_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($produto['telefone_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($produto['data_venda']); ?></td>
                            <td><?php echo $data_remarketing; ?></td>
                            <td>
                                <a href="<?php echo $link_whatsapp; ?>" class="whatsapp-link" target="_blank">
                                    <img src="https://i.pinimg.com/originals/1e/a8/09/1ea80946cd8a2aa42e59bcbc81bd9e92.png" class="whatsapp-icon" alt="WhatsApp Icon"> WhatsApp
                                </a>
                            </td>
                        </tr>
            <?php endforeach; endif; endforeach; ?>
        </tbody>
    </table>

    <h5>Clientes Não Elegíveis para Remarketing</h5>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Nome do Produto</th>
                <th>Nome do Cliente</th>
                <th>Telefone do Cliente</th>
                <th>Data da Venda</th>
                <th>Data de Remarketing</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($clientes_vendas as $vendas_cliente):
                $data_remarketing = calcular_remarketing($vendas_cliente);
                
                // Exibe os clientes não elegíveis para remarketing
                if ($data_remarketing === null): 
                    foreach ($vendas_cliente as $produto):
            ?>
                        <tr class="nao-elegivel">
                            <td><?php echo htmlspecialchars($produto['nome_produto']); ?></td>
                            <td><?php echo htmlspecialchars($produto['nome_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($produto['telefone_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($produto['data_venda']); ?></td>
                            <td>N/A</td>
                        </tr>
            <?php endforeach; endif; endforeach; ?>
        </tbody>
    </table>

</div>
<?php include 'footer.php'; ?>
</body>
</html>
