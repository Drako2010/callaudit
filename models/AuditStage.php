<?php
/**
 * CallAudit - modelo de etapas del flujo de auditoría.
 * Todas las consultas quedan limitadas por tenant_id.
 */
require_once __DIR__ . '/../config/database.php';

class AuditStage
{
    private ?PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function listar(int $tenantId, int $campaignId): array
    {
        if (!$this->db || $tenantId <= 0 || $campaignId <= 0) return [];
        $sql = "SELECT s.id, s.tenant_id, s.campaign_id, s.stage_number,
                       s.name, s.form_version_id, s.human_enabled, s.ai_enabled,
                       s.ai_required, s.status, s.created_at,
                       f.name AS form_name, v.version_number
                FROM audit_workflow_stages s
                INNER JOIN audit_form_versions v
                    ON v.tenant_id=s.tenant_id AND v.id=s.form_version_id
                INNER JOIN audit_forms f
                    ON f.tenant_id=v.tenant_id AND f.id=v.form_id
                WHERE s.tenant_id=:tenant AND s.campaign_id=:campaign
                ORDER BY s.stage_number, s.id";
        $q=$this->db->prepare($sql);
        $q->execute([':tenant'=>$tenantId, ':campaign'=>$campaignId]);
        return $q->fetchAll();
    }

    public function campanias(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) return [];
        $q=$this->db->prepare("SELECT id,name FROM campaigns WHERE tenant_id=:tenant ORDER BY name");
        $q->execute([':tenant'=>$tenantId]);
        return $q->fetchAll();
    }

    public function versionesPublicadas(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) return [];
        $sql="SELECT v.id, f.name AS form_name, v.version_number
              FROM audit_form_versions v
              INNER JOIN audit_forms f
                ON f.tenant_id=v.tenant_id AND f.id=v.form_id
              WHERE v.tenant_id=:tenant AND v.status='PUBLISHED'
                AND f.status='ACTIVE'
              ORDER BY f.name, v.version_number DESC";
        $q=$this->db->prepare($sql);
        $q->execute([':tenant'=>$tenantId]);
        return $q->fetchAll();
    }

    public function obtener(int $tenantId, int $id): ?array
    {
        if (!$this->db || $tenantId<=0 || $id<=0) return null;
        $q=$this->db->prepare("SELECT * FROM audit_workflow_stages WHERE tenant_id=:tenant AND id=:id LIMIT 1");
        $q->execute([':tenant'=>$tenantId, ':id'=>$id]);
        $row=$q->fetch();
        return $row ?: null;
    }

    public function guardar(int $tenantId, int $campaignId, array $data, int $userId, ?int $id=null): array
    {
        if (!$this->db) return ['success'=>false,'message'=>'No se pudo conectar con la base de datos.'];
        if ($tenantId<=0 || $campaignId<=0 || $userId<=0) return ['success'=>false,'message'=>'Contexto inválido.'];
        $number=filter_var($data['stage_number'] ?? null,FILTER_VALIDATE_INT);
        $formId=filter_var($data['form_version_id'] ?? null,FILTER_VALIDATE_INT);
        $name=trim((string)($data['name'] ?? ''));
        $human=isset($data['human_enabled']) ? 1 : 0;
        $ai=isset($data['ai_enabled']) ? 1 : 0;
        $required=isset($data['ai_required']) ? 1 : 0;
        $status=($data['status'] ?? 'ACTIVE')==='INACTIVE'?'INACTIVE':'ACTIVE';

        if (!$number || $number<1) return ['success'=>false,'message'=>'El número de etapa debe ser mayor que cero.'];
        if ($name==='' || mb_strlen($name,'UTF-8')>150) return ['success'=>false,'message'=>'El nombre es obligatorio y no debe superar 150 caracteres.'];
        if (!$formId || $formId<1) return ['success'=>false,'message'=>'Selecciona una versión publicada de formulario.'];
        if (!$human && !$ai) return ['success'=>false,'message'=>'Cada etapa debe tener al menos un ejecutor: humano o IA.'];
        if ($required && !$ai) return ['success'=>false,'message'=>'No puedes exigir IA si la IA está desactivada en esta etapa.'];

        try {
            // Confirmar que campaña y versión pertenecen a la misma empresa.
            $q=$this->db->prepare("SELECT id FROM campaigns WHERE id=:campaign AND tenant_id=:tenant LIMIT 1");
            $q->execute([':campaign'=>$campaignId, ':tenant'=>$tenantId]);
            if (!$q->fetch()) return ['success'=>false,'message'=>'La campaña no pertenece a la empresa seleccionada.'];
            $q=$this->db->prepare("SELECT id FROM audit_form_versions WHERE id=:version AND tenant_id=:tenant AND status='PUBLISHED' LIMIT 1");
            $q->execute([':version'=>$formId, ':tenant'=>$tenantId]);
            if (!$q->fetch()) return ['success'=>false,'message'=>'La versión del formulario no existe, no está publicada o pertenece a otra empresa.'];

            $sqlCheck="SELECT id FROM audit_workflow_stages
                       WHERE tenant_id=:tenant AND campaign_id=:campaign AND stage_number=:number";
            $params=[':tenant'=>$tenantId,':campaign'=>$campaignId,':number'=>$number];
            if ($id) { $sqlCheck.=" AND id<>:id"; $params[':id']=$id; }
            $sqlCheck.=" LIMIT 1";
            $q=$this->db->prepare($sqlCheck); $q->execute($params);
            if ($q->fetch()) return ['success'=>false,'message'=>'Ya existe una etapa con ese número para esta campaña.'];

            if ($id) {
                $existing=$this->obtener($tenantId,$id);
                if (!$existing || (int)$existing['campaign_id']!==$campaignId)
                    return ['success'=>false,'message'=>'La etapa no existe en esta campaña.'];
                $sql="UPDATE audit_workflow_stages
                      SET stage_number=:number,name=:name,form_version_id=:version,
                          human_enabled=:human,ai_enabled=:ai,ai_required=:required,status=:status
                      WHERE tenant_id=:tenant AND campaign_id=:campaign AND id=:id";
                $params=[':number'=>$number,':name'=>$name,':version'=>$formId,':human'=>$human,
                         ':ai'=>$ai,':required'=>$required,':status'=>$status,
                         ':tenant'=>$tenantId,':campaign'=>$campaignId,':id'=>$id];
                $q=$this->db->prepare($sql); $q->execute($params);
                return ['success'=>true,'message'=>'Etapa actualizada correctamente.'];
            }
            $sql="INSERT INTO audit_workflow_stages
                  (tenant_id,campaign_id,stage_number,name,form_version_id,human_enabled,ai_enabled,ai_required,status,created_by)
                  VALUES (:tenant,:campaign,:number,:name,:version,:human,:ai,:required,:status,:user)";
            $q=$this->db->prepare($sql);
            $q->execute([':tenant'=>$tenantId,':campaign'=>$campaignId,':number'=>$number,':name'=>$name,
                         ':version'=>$formId,':human'=>$human,':ai'=>$ai,':required'=>$required,
                         ':status'=>$status,':user'=>$userId]);
            return ['success'=>true,'message'=>'Etapa creada correctamente.'];
        } catch (PDOException $e) {
            error_log('CallAudit AuditStage::guardar: '.$e->getMessage());
            return ['success'=>false,'message'=>'No se pudo guardar la etapa. Revisa el log del servidor.'];
        }
    }
}
