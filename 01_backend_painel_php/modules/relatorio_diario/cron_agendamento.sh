#!/bin/bash
php /var/www/html/modules/relatorio_diario/gerar_mensagem.php > /tmp/relatorio.txt
php /var/www/html/modules/relatorio_diario/enviar_whatsapp_api.php "$(cat /tmp/relatorio.txt)"
php /var/www/html/modules/cartao_fidelidade/painel_envio_cron.php
