⚠️ NOTA (layout reorganizado)
Este documento é legado e pode citar estruturas antigas.
No ZIP atual, o painel PHP fica em: 01_backend_painel_php/
Deploy recomendado: 06_deploy_infra/scripts/install.sh
---

README - REDE ALABAMA - V23 ULTRA

Base: V22 ULTRA Quantum Patched

Este pacote adiciona APENAS três conjuntos de otimizações sobre o V22:

1) Versionamento de fluxos + rollback
   - Novo helper: flows_versioning.php
   - Nova tela:  flows_versions.php
   - Permite criar snapshots manuais dos fluxos (whatsapp_flows) e restaurar versões anteriores.

   Tabela sugerida (criar manualmente, se desejar utilizar o recurso):
   ------------------------------------------------------------
   CREATE TABLE IF NOT EXISTS whatsapp_flow_versions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       flow_id INT NOT NULL,
       version_number INT NOT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       created_by_user_id INT NULL,
       reason VARCHAR(255) NULL,
       flow_snapshot_json LONGTEXT NOT NULL,
       INDEX idx_flow_versions_flow_id (flow_id),
       CONSTRAINT fk_flow_versions_flow FOREIGN KEY (flow_id)
           REFERENCES whatsapp_flows(id)
           ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ------------------------------------------------------------

   Observações:
   - Se a tabela não existir, o código ignora silenciosamente o versionamento (sistema continua operando).
   - O rollback restaura dados básicos do fluxo (nome, descrição, status, target_segment) e steps (whatsapp_flow_steps).
   - Execuções em andamento de um fluxo não são automaticamente migradas; recomenda-se cancelar execuções antigas antes de aplicar rollback em produção.

2) Core Automation
   - Novo núcleo de automação desacoplado do restante do painel:
       * automation_core.php
       * automation_runner.php
   - Implementa:
       * Fila de eventos de automação (automation_events)
       * Carregamento de regras (automation_rules)
       * Processamento básico de eventos e execução de ações

   Tabelas sugeridas:
   ------------------------------------------------------------
   CREATE TABLE IF NOT EXISTS automation_rules (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(150) NOT NULL,
       description TEXT NULL,
       event_key VARCHAR(100) NOT NULL,
       is_active TINYINT(1) NOT NULL DEFAULT 1,
       conditions_json LONGTEXT NULL,
       action_type VARCHAR(50) NOT NULL,
       action_payload_json LONGTEXT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
           ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   CREATE TABLE IF NOT EXISTS automation_events (
       id INT AUTO_INCREMENT PRIMARY KEY,
       event_key VARCHAR(100) NOT NULL,
       payload_json LONGTEXT NULL,
       status VARCHAR(20) NOT NULL DEFAULT 'pending',
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       processed_at DATETIME NULL,
       last_error TEXT NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   CREATE TABLE IF NOT EXISTS automation_rule_executions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       rule_id INT NOT NULL,
       event_id INT NOT NULL,
       status VARCHAR(20) NOT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       message TEXT NULL,
       INDEX idx_rule_exec_rule (rule_id),
       INDEX idx_rule_exec_event (event_id),
       CONSTRAINT fk_automation_rule_exec_rule FOREIGN KEY (rule_id)
           REFERENCES automation_rules(id)
           ON DELETE CASCADE,
       CONSTRAINT fk_automation_rule_exec_event FOREIGN KEY (event_id)
           REFERENCES automation_events(id)
           ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ------------------------------------------------------------

   Observações:
   - Se as tabelas não existirem, os métodos de automação falham de forma silenciosa (logam erro, mas não quebram o painel).
   - O runner pode ser chamado via CLI:
       php automation_runner.php
     ou embutido em crons já existentes.

3) Rule Engine simples
   - Novo helper com avaliador de regras declarativas em JSON:
       * rule_engine_simple.php
       * automation_rules.php (tela de CRUD simplificado para regras)
   - Condições em formato:
       [
         {
           "field": "event.payload.segmento",
           "op": "equals",
           "value": "D0_D7"
         }
       ]
   - Ações disponíveis (núcleo):
       * log_only
       * placeholder para acionar fluxos / jobs (pontos de extensão marcados no código)

   Observações:
   - O engine não faz eval() de código arbitrário.
   - Os caminhos de campo são resolvidos via notação com ponto (ex.: event.payload.x.y).

IMPORTANTE:
- Nenhum schema existente foi alterado no código; apenas são referenciadas novas tabelas opcionais.
- O painel continua operando mesmo sem criar as tabelas de versionamento/automação.
- O foco desta versão é oferecer a infraestrutura mínima e estável para:
    * versionar fluxos,
    * ter rollback,
    * centralizar automação e regras em um núcleo extensível.

Versão: V23 ULTRA
Data: 2025-11-30
Base: adm.redealabama_v22_ultra_quantum_patched.zip
