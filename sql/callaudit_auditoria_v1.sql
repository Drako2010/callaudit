
-- ============================================================
-- CallAudit - Migracion del modulo de auditoria v1
-- Compatible con MariaDB 10.4 / MySQL InnoDB.
--
-- PRECAUCIONES:
-- 1. Realizar backup completo antes de ejecutar.
-- 2. Seleccionar la base de datos callaudit en phpMyAdmin.
-- 3. Ejecutar una sola vez sobre la base actual.
-- 4. No modifica roles ni asignaciones de permisos existentes.
-- 5. Las operaciones DDL de MariaDB no ofrecen rollback global.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. Formularios configurables por empresa
-- ------------------------------------------------------------

CREATE TABLE audit_forms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('ACTIVE','INACTIVE','ARCHIVED')
        NOT NULL DEFAULT 'ACTIVE',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_forms_tenant_id (tenant_id, id),
    KEY idx_audit_forms_tenant_status (tenant_id, status),
    CONSTRAINT fk_audit_forms_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_audit_forms_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Versiones de formularios
-- Las versiones publicadas no deben editarse desde la aplicacion.
-- ------------------------------------------------------------

CREATE TABLE audit_form_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    form_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    status ENUM('DRAFT','PUBLISHED','RETIRED')
        NOT NULL DEFAULT 'DRAFT',
    passing_score DECIMAL(7,2) NULL,
    na_policy ENUM('EXCLUDE','ZERO','FULL')
        NOT NULL DEFAULT 'EXCLUDE',
    published_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_form_version_number
        (tenant_id, form_id, version_number),
    UNIQUE KEY uk_audit_form_version_tenant_id
        (tenant_id, id),
    CONSTRAINT fk_aformv_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_aformv_form
        FOREIGN KEY (tenant_id, form_id)
        REFERENCES audit_forms(tenant_id, id),
    CONSTRAINT fk_aformv_publisher
        FOREIGN KEY (published_by) REFERENCES users(id),
    CONSTRAINT fk_aformv_creator
        FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT chk_aformv_passing_score
        CHECK (passing_score IS NULL OR
               (passing_score >= 0 AND passing_score <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Preguntas y criterios versionados
-- El texto queda guardado en la version historica del formulario.
-- ------------------------------------------------------------

CREATE TABLE audit_form_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    form_version_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 1,
    question_text VARCHAR(1000) NOT NULL,
    help_text TEXT NULL,
    answer_type ENUM(
        'YES_NO',
        'SCALE',
        'SINGLE_CHOICE',
        'MULTIPLE_CHOICE',
        'TEXT',
        'NUMBER'
    ) NOT NULL DEFAULT 'YES_NO',
    options_json LONGTEXT NULL,
    weight DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    is_critical TINYINT(1) NOT NULL DEFAULT 0,
    required_answer TINYINT(1) NOT NULL DEFAULT 1,
    allow_na TINYINT(1) NOT NULL DEFAULT 0,
    na_score DECIMAL(8,2) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_question_position
        (tenant_id, form_version_id, position),
    UNIQUE KEY uk_audit_question_tenant_id
        (tenant_id, id),
    CONSTRAINT fk_aquestion_version
        FOREIGN KEY (tenant_id, form_version_id)
        REFERENCES audit_form_versions(tenant_id, id),
    CONSTRAINT chk_aquestion_weight CHECK (weight >= 0),
    CONSTRAINT chk_aquestion_na_score
        CHECK (na_score IS NULL OR na_score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Etapas configurables por empresa/campana
-- Cada etapa puede utilizar una version concreta del formulario.
-- ------------------------------------------------------------

CREATE TABLE audit_workflow_stages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    stage_number INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    form_version_id BIGINT UNSIGNED NOT NULL,
    human_enabled TINYINT(1) NOT NULL DEFAULT 1,
    ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ai_required TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_stage_number
        (tenant_id, campaign_id, stage_number),
    UNIQUE KEY uk_audit_stage_tenant_id (tenant_id, id),
    CONSTRAINT fk_astage_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_astage_campaign
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    CONSTRAINT fk_astage_form_version
        FOREIGN KEY (tenant_id, form_version_id)
        REFERENCES audit_form_versions(tenant_id, id),
    CONSTRAINT fk_astage_creator
        FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT chk_astage_number CHECK (stage_number > 0),
    CONSTRAINT chk_astage_executor
        CHECK (human_enabled = 1 OR ai_enabled = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Fuentes telefonicas
-- Admite varias plataformas/servidores por empresa.
-- No guardar contrasenas en texto plano.
-- ------------------------------------------------------------

CREATE TABLE telephony_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    platform ENUM(
        'ASTERISK',
        'VICIDIAL',
        'ISSABEL',
        'GENERIC_API',
        'DATABASE',
        'CSV',
        'EXCEL',
        'MANUAL',
        'OTHER'
    ) NOT NULL DEFAULT 'OTHER',
    connection_mode ENUM(
        'API',
        'READONLY_DATABASE',
        'FILE_IMPORT',
        'MANUAL',
        'OTHER'
    ) NOT NULL DEFAULT 'MANUAL',
    host VARCHAR(255) NULL,
    port INT UNSIGNED NULL,
    database_name VARCHAR(150) NULL,
    configuration_json LONGTEXT NULL,
    encrypted_secret LONGBLOB NULL,
    secret_key_version VARCHAR(50) NULL,
    status ENUM(
        'ACTIVE',
        'INACTIVE',
        'ERROR',
        'PENDING_TEST'
    ) NOT NULL DEFAULT 'PENDING_TEST',
    last_test_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL,
    last_error_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_telephony_source_tenant_id (tenant_id, id),
    KEY idx_telephony_source_status (tenant_id, status),
    CONSTRAINT fk_tsource_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_tsource_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Registro de llamadas y metadatos
-- El audio se almacena fuera de la carpeta publica web.
-- audio_storage_key apunta a almacenamiento controlado.
-- ------------------------------------------------------------

CREATE TABLE audit_calls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    external_call_id VARCHAR(255) NULL,
    agent_external_id VARCHAR(255) NULL,
    agent_user_id BIGINT UNSIGNED NULL,
    phone_masked VARCHAR(100) NULL,
    started_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL,
    audio_storage_key VARCHAR(1000) NULL,
    audio_sha256 CHAR(64) NULL,
    audio_mime_type VARCHAR(100) NULL,
    audio_size_bytes BIGINT UNSIGNED NULL,
    audio_status ENUM(
        'NOT_RECEIVED',
        'PENDING',
        'AVAILABLE',
        'MISSING',
        'ERROR',
        'DELETED'
    ) NOT NULL DEFAULT 'NOT_RECEIVED',
    transcript_storage_key VARCHAR(1000) NULL,
    metadata_json LONGTEXT NULL,
    processing_status ENUM(
        'NEW',
        'READY',
        'IN_PROGRESS',
        'COMPLETED',
        'REVIEW',
        'ERROR',
        'ARCHIVED'
    ) NOT NULL DEFAULT 'NEW',
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_call_tenant_id (tenant_id, id),
    UNIQUE KEY uk_audit_call_external
        (tenant_id, source_id, external_call_id),
    KEY idx_audit_calls_campaign_date
        (tenant_id, campaign_id, started_at),
    KEY idx_audit_calls_processing
        (tenant_id, processing_status, started_at),
    KEY idx_audit_calls_agent
        (tenant_id, agent_user_id, started_at),
    CONSTRAINT fk_acall_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_acall_campaign
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    CONSTRAINT fk_acall_source
        FOREIGN KEY (tenant_id, source_id)
        REFERENCES telephony_sources(tenant_id, id),
    CONSTRAINT fk_acall_agent
        FOREIGN KEY (agent_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Politicas de seleccion de llamadas
-- Se pueden combinar manual, aleatoria, reglas y automatica.
-- La logica de ejecucion se implementa en PHP/worker.
-- ------------------------------------------------------------

CREATE TABLE audit_selection_policies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    method ENUM(
        'MANUAL',
        'RANDOM',
        'RULES',
        'AUTOMATIC'
    ) NOT NULL,
    configuration_json LONGTEXT NULL,
    priority INT NOT NULL DEFAULT 100,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_selection_policy_tenant_id (tenant_id, id),
    KEY idx_selection_policy_campaign
        (tenant_id, campaign_id, enabled, priority),
    CONSTRAINT fk_spolicy_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_spolicy_campaign
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    CONSTRAINT fk_spolicy_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. Trabajos en segundo plano
-- Incluye reintentos e idempotencia para evitar duplicados.
-- ------------------------------------------------------------

CREATE TABLE audit_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    job_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(191) NOT NULL,
    payload_json LONGTEXT NULL,
    status ENUM(
        'PENDING',
        'RUNNING',
        'COMPLETED',
        'RETRY',
        'FAILED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'PENDING',
    priority INT NOT NULL DEFAULT 100,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    locked_by VARCHAR(150) NULL,
    completed_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL,
    last_error_message VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_job_idempotency
        (tenant_id, idempotency_key),
    KEY idx_audit_jobs_queue
        (status, available_at, priority, id),
    KEY idx_audit_jobs_tenant
        (tenant_id, status, created_at),
    CONSTRAINT fk_ajob_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT chk_audit_job_attempts
        CHECK (max_attempts > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. Tareas de auditoria por llamada y etapa
-- ------------------------------------------------------------

CREATE TABLE audit_tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    call_id BIGINT UNSIGNED NOT NULL,
    stage_id BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NULL,
    assigned_by BIGINT UNSIGNED NULL,
    status ENUM(
        'PENDING',
        'ASSIGNED',
        'IN_PROGRESS',
        'SUBMITTED',
        'REVIEW',
        'COMPLETED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'PENDING',
    priority INT NOT NULL DEFAULT 100,
    due_at DATETIME NULL,
    started_at DATETIME NULL,
    submitted_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_task_tenant_id (tenant_id, id),
    UNIQUE KEY uk_audit_task_call_stage (tenant_id, call_id, stage_id),
    KEY idx_audit_tasks_assignee
        (tenant_id, assigned_to, status, due_at),
    KEY idx_audit_tasks_queue
        (tenant_id, status, priority, created_at),
    CONSTRAINT fk_atask_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_atask_call
        FOREIGN KEY (tenant_id, call_id)
        REFERENCES audit_calls(tenant_id, id),
    CONSTRAINT fk_atask_stage
        FOREIGN KEY (tenant_id, stage_id)
        REFERENCES audit_workflow_stages(tenant_id, id),
    CONSTRAINT fk_atask_assignee
        FOREIGN KEY (assigned_to) REFERENCES users(id),
    CONSTRAINT fk_atask_assigner
        FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. Resultados generales de auditoria
-- AI y humano se guardan por separado.
-- ------------------------------------------------------------

CREATE TABLE audit_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    evaluator_type ENUM('HUMAN','AI') NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NULL,
    ai_provider VARCHAR(100) NULL,
    ai_model VARCHAR(150) NULL,
    verdict ENUM(
        'PASS',
        'FAIL',
        'REVIEW',
        'INCONCLUSIVE'
    ) NOT NULL DEFAULT 'REVIEW',
    score DECIMAL(7,2) NULL,
    analysis LONGTEXT NULL,
    strengths_json LONGTEXT NULL,
    improvement_json LONGTEXT NULL,
    confidence DECIMAL(6,5) NULL,
    status ENUM('DRAFT','SUBMITTED','SUPERSEDED')
        NOT NULL DEFAULT 'DRAFT',
    evaluated_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_result_tenant_id (tenant_id, id),
    KEY idx_audit_results_task
        (tenant_id, task_id, evaluator_type, status),
    CONSTRAINT fk_aresult_task
        FOREIGN KEY (tenant_id, task_id)
        REFERENCES audit_tasks(tenant_id, id),
    CONSTRAINT fk_aresult_evaluator
        FOREIGN KEY (evaluator_user_id) REFERENCES users(id),
    CONSTRAINT chk_aresult_score
        CHECK (score IS NULL OR (score >= 0 AND score <= 100)),
    CONSTRAINT chk_aresult_confidence
        CHECK (confidence IS NULL OR
               (confidence >= 0 AND confidence <= 1)),
    CONSTRAINT chk_aresult_evaluator
        CHECK (
            (evaluator_type = 'HUMAN' AND evaluator_user_id IS NOT NULL)
            OR
            (evaluator_type = 'AI' AND evaluator_user_id IS NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. Respuestas individuales por pregunta
-- ------------------------------------------------------------

CREATE TABLE audit_result_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    result_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    answer_value LONGTEXT NULL,
    numeric_score DECIMAL(8,2) NULL,
    is_na TINYINT(1) NOT NULL DEFAULT 0,
    critical_failure TINYINT(1) NOT NULL DEFAULT 0,
    comment TEXT NULL,
    evidence_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_aresult_answer
        (tenant_id, result_id, question_id),
    CONSTRAINT fk_ra_result
        FOREIGN KEY (tenant_id, result_id)
        REFERENCES audit_results(tenant_id, id),
    CONSTRAINT fk_ra_question
        FOREIGN KEY (tenant_id, question_id)
        REFERENCES audit_form_questions(tenant_id, id),
    CONSTRAINT chk_ra_numeric_score
        CHECK (numeric_score IS NULL OR numeric_score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. Configuracion de proveedores IA por empresa
-- No incluye claves en texto plano.
-- La aplicacion debe cifrar antes de guardar encrypted_secret.
-- ------------------------------------------------------------

CREATE TABLE ai_provider_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider_code VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    billing_mode ENUM('FREE','PAID','MIXED')
        NOT NULL DEFAULT 'FREE',
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    authorized_paid_fallback TINYINT(1) NOT NULL DEFAULT 0,
    monthly_budget DECIMAL(12,4) NULL,
    daily_budget DECIMAL(12,4) NULL,
    monthly_limit INT UNSIGNED NULL,
    model_name VARCHAR(150) NULL,
    encrypted_secret LONGBLOB NULL,
    secret_key_version VARCHAR(50) NULL,
    configuration_json LONGTEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ai_provider_tenant (tenant_id, provider_code),
    CONSTRAINT fk_aip_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_aip_creator
        FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT chk_aip_monthly_budget
        CHECK (monthly_budget IS NULL OR monthly_budget >= 0),
    CONSTRAINT chk_aip_daily_budget
        CHECK (daily_budget IS NULL OR daily_budget >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. Registro de actividad del modulo
-- No guardar contrasenas, tokens, claves ni audio en el log.
-- ------------------------------------------------------------

CREATE TABLE audit_activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    description VARCHAR(1000) NULL,
    metadata_json LONGTEXT NULL,
    source_ip VARCHAR(45) NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aactivity_tenant_date (tenant_id, occurred_at),
    KEY idx_aactivity_entity
        (tenant_id, entity_type, entity_id, occurred_at),
    KEY idx_aactivity_actor (tenant_id, actor_user_id, occurred_at),
    CONSTRAINT fk_aactivity_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_aactivity_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. Configuracion general del flujo por empresa
-- ------------------------------------------------------------

CREATE TABLE audit_tenant_settings (
    tenant_id BIGINT UNSIGNED NOT NULL,
    default_audio_retention_days INT UNSIGNED NULL,
    require_human_review_after_ai TINYINT(1) NOT NULL DEFAULT 0,
    allow_ai_processing TINYINT(1) NOT NULL DEFAULT 0,
    audio_storage_backend ENUM('LOCAL','NAS','S3_COMPATIBLE')
        NOT NULL DEFAULT 'LOCAL',
    audio_storage_config_json LONGTEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_atsettings_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_atsettings_updater
        FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FIN DE MIGRACION
-- ------------------------------------------------------------
