<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['snapshot' => 'array']; }
    public function user() { return $this->belongsTo(User::class); }

    public static function reportingMonth(?string $from, ?string $to): ?string
    {
        $format = '/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])-[0-9]{2}\z/';
        if (!preg_match($format, $from ?? '') || !preg_match($format, $to ?? '')) return null;

        $month = substr($from, 0, 7);
        return $month === substr($to, 0, 7) ? $month : null;
    }
}
