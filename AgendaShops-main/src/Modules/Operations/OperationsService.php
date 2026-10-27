<?php
declare(strict_types=1);
namespace AgendaShops\Modules\Operations;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Modules\Catalog\CatalogService;
use AgendaShops\Domain\{Input, Problem};

final class OperationsService
{
    public function __construct(private Database $db, private CatalogService $catalog) {}
    public function category(array $user, array $data): void
    {
        $this->catalog->admin($user);
        $id = trim((string)($data['id'] ?? ''));
        $name = Input::text($data, 'name', 2, 100);
        $parent = trim((string)($data['parent_id'] ?? '')) ?: null;
        if ($parent && (!$this->db->one('SELECT id FROM categories WHERE id=? AND parent_id IS NULL', [$parent]) || $parent === $id)) { throw new Problem('Categoria superior inválida.'); }
        $order = Input::integer($data, 'sort_order', 0, 9999);
        if ($id) {
            $this->db->execute('UPDATE categories SET name=?,parent_id=?,sort_order=?,active=? WHERE id=?', [$name,$parent,$order,isset($data['active']) ? 1 : 0,$id]);
        } else { $id = $this->db->insert('categories', ['name'=>$name,'parent_id'=>$parent,'sort_order'=>$order,'active'=>isset($data['active']) ? 1 : 0]); }
        $this->catalog->audit($user,'CATEGORY_SAVED',$id);
    }
    public function staff(array $user, array $data): void
    {
        $businessId = Input::text($data, 'business_id');
        $this->catalog->membership($user,$businessId,true);
        $email = Input::email($data);
        $staff = $this->db->one('SELECT * FROM users WHERE email=? AND active=1', [$email]);
        if (!$staff || $staff['id'] === $user['id']) { throw new Problem('Informe o e-mail de outra conta já cadastrada.'); }
        if (($data['remove'] ?? '') === '1') {
            $this->db->execute("DELETE FROM memberships WHERE business_id=? AND user_id=? AND role='PARTNER_STAFF'", [$businessId,$staff['id']]);
        } else {
            $this->db->execute("INSERT INTO memberships(id,business_id,user_id,role) VALUES(?,?,?,'PARTNER_STAFF') ON CONFLICT(business_id,user_id) DO NOTHING", [Database::id(),$businessId,$staff['id']]);
        }
        $this->catalog->audit($user,'STAFF_CHANGED',$businessId);
    }
    public function block(array $user, array $data): void
    {
        $professionalId = Input::text($data,'professional_id');
        $professional = $this->db->one('SELECT * FROM professionals WHERE id=?', [$professionalId]);
        if (!$professional) { throw new Problem('Profissional não encontrado.',404); }
        $this->catalog->membership($user,$professional['business_id']);
        $business = $this->db->one('SELECT * FROM businesses WHERE id=?', [$professional['business_id']]);
        $zone = new \DateTimeZone($business['timezone']);
        if (($data['remove'] ?? '') === '1') {
            $this->db->execute('DELETE FROM schedule_blocks WHERE id=? AND professional_id=?', [Input::text($data,'block_id'),$professionalId]);
            return;
        }
        $start = Input::date(Input::text($data,'starts_at'),$zone)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $end = Input::date(Input::text($data,'ends_at'),$zone)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        if ($end <= $start) { throw new Problem('O fim deve ser depois do início.'); }
        $reason = Input::text($data,'reason',3,255);
        $this->db->transaction(function () use ($professionalId,$start,$end,$reason): void {
            $this->db->lockProfessional($professionalId);
            if ($this->db->one("SELECT id FROM bookings WHERE professional_id=? AND status IN('CONFIRMED','IN_PROGRESS') AND starts_at<? AND ends_at>?", [$professionalId,$end,$start])) { throw new Problem('Há atendimento confirmado nesse intervalo. Cancele ou reagende antes.',409); }
            $this->db->insert('schedule_blocks',['professional_id'=>$professionalId,'starts_at'=>$start,'ends_at'=>$end,'reason'=>$reason]);
        });
    }
    public function upload(array $user, array $data, array $file): void
    {
        $serviceId = Input::text($data,'service_id');
        $service = $this->db->one('SELECT * FROM services WHERE id=?',[$serviceId]);
        if (!$service) { throw new Problem('Serviço não encontrado.',404); }
        $this->catalog->membership($user,$service['business_id'],true);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 4 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'] ?? '')) { throw new Problem('Envie uma imagem de até 4 MB.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
        $size = getimagesize($file['tmp_name']);
        if (!$extension || !$size || $size[0] > 6000 || $size[1] > 6000) { throw new Problem('Use JPG, PNG ou WebP com até 6000 pixels por lado.'); }
        $dir = Database::storage() . '/media';
        if (!is_dir($dir)) { mkdir($dir,0700,true); }
        $name = Database::id() . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'],$dir . '/' . $name)) { throw new \RuntimeException('Upload failed.'); }
        $this->db->execute('UPDATE services SET image=? WHERE id=?',[$name,$serviceId]);
    }
}
