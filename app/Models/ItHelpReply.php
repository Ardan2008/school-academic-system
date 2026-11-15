<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Balasan Admin (riwayat tindak lanjut) atas permintaan bantuan dari
 * pop-up "Hubungi Tim IT Sekolah" di landing page.
 *
 * @property int $id
 * @property int $it_help_request_id
 * @property int|null $user_id
 * @property string $pesan
 * @property string $status_pengiriman
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ItHelpReply extends Model
{
    protected $fillable = [
        'it_help_request_id',
        'user_id',
        'pesan',
        'status_pengiriman',
    ];

    public function itHelpRequest()
    {
        return $this->belongsTo(ItHelpRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
