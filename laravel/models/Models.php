<?php
namespace App\Models;

// ─────────────────────────────────────────────
//  Kachankawal Rural Municipality – All Models
// ─────────────────────────────────────────────

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

// ══════════════════════════════════
// User
// ══════════════════════════════════
class User extends Authenticatable implements JWTSubject {
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = [
        'name','phone','email','password','ward_no',
        'citizenship_no','profile_photo','role',
        'is_verified','is_active','device_token',
    ];

    protected $hidden   = ['password'];
    protected $casts    = ['is_verified' => 'boolean', 'is_active' => 'boolean'];

    public function getJWTIdentifier()       { return $this->getKey(); }
    public function getJWTCustomClaims()     { return ['role' => $this->role]; }

    public function ward()           { return $this->belongsTo(Ward::class, 'ward_no', 'ward_no'); }
    public function serviceRequests(){ return $this->hasMany(ServiceRequest::class); }
    public function complaints()     { return $this->hasMany(Complaint::class); }

    public function isMunicipalityAdmin() { return $this->role === 'municipality_admin'; }
    public function isWardAdmin()         { return $this->role === 'ward_admin'; }
    public function isCitizen()           { return $this->role === 'citizen'; }
}

// ══════════════════════════════════
// OtpToken
// ══════════════════════════════════
class OtpToken extends Model {
    protected $fillable = ['phone','otp','expires_at','used'];
    protected $casts    = ['expires_at' => 'datetime', 'used' => 'boolean'];

    public function isExpired()  { return $this->expires_at->isPast(); }
    public function isValid()    { return !$this->used && !$this->isExpired(); }
}

// ══════════════════════════════════
// Ward
// ══════════════════════════════════
class Ward extends Model {
    protected $fillable = [
        'ward_no','ward_name','secretary_name',
        'secretary_phone','secretary_email','address',
        'office_photo','is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function users()           { return $this->hasMany(User::class, 'ward_no', 'ward_no'); }
    public function notices()         { return $this->hasMany(Notice::class, 'ward_no', 'ward_no'); }
    public function representatives() { return $this->hasMany(Representative::class, 'ward_no', 'ward_no'); }
}

// ══════════════════════════════════
// Representative
// ══════════════════════════════════
class Representative extends Model {
    protected $fillable = [
        'name','position','ward_no','party',
        'phone','email','photo','bio','is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function ward() { return $this->belongsTo(Ward::class, 'ward_no', 'ward_no'); }
}

// ══════════════════════════════════
// Notice
// ══════════════════════════════════
class Notice extends Model {
    use SoftDeletes;

    protected $fillable = [
        'title','body','type','ward_no','attachment',
        'effective_date','expiry_date','push_sent','is_active','created_by',
    ];
    protected $casts = [
        'effective_date' => 'date',
        'expiry_date'    => 'date',
        'push_sent'      => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($q)              { return $q->where('is_active', true); }
    public function scopeForWard($q, $wardNo)    { return $q->where(fn($w) => $w->whereNull('ward_no')->orWhere('ward_no', $wardNo)); }
    public function scopeOfType($q, $type)       { return $q->where('type', $type); }
}

// ══════════════════════════════════
// ServiceRequest
// ══════════════════════════════════
class ServiceRequest extends Model {
    use SoftDeletes;

    protected $fillable = [
        'tracking_no','user_id','service_type','ward_no',
        'form_data','status','remarks','assigned_to','resolved_at',
    ];
    protected $casts = [
        'form_data'   => 'array',
        'resolved_at' => 'datetime',
    ];

    protected static function booted() {
        static::creating(fn($m) => $m->tracking_no = strtoupper('SR-'.date('Ymd').'-'.random_int(1000,9999)));
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function documents(){ return $this->hasMany(ServiceDocument::class); }

    public function scopeForUser($q, $userId)   { return $q->where('user_id', $userId); }
    public function scopeByStatus($q, $status)  { return $q->where('status', $status); }
    public function scopeByWard($q, $wardNo)    { return $q->where('ward_no', $wardNo); }
}

// ══════════════════════════════════
// ServiceDocument
// ══════════════════════════════════
class ServiceDocument extends Model {
    protected $fillable = ['service_request_id','document_type','file_path','original_name','file_size'];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
}

// ══════════════════════════════════
// Complaint
// ══════════════════════════════════
class Complaint extends Model {
    use SoftDeletes;

    protected $fillable = [
        'tracking_no','user_id','category','title','description',
        'ward_no','latitude','longitude','location_description',
        'status','assigned_to','resolution_note','resolved_at',
    ];
    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'resolved_at' => 'datetime',
    ];

    protected static function booted() {
        static::creating(fn($m) => $m->tracking_no = strtoupper('CP-'.date('Ymd').'-'.random_int(1000,9999)));
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function photos()   { return $this->hasMany(ComplaintPhoto::class); }

    public function scopeByCategory($q, $cat)  { return $q->where('category', $cat); }
    public function scopeByStatus($q, $status) { return $q->where('status', $status); }
    public function scopeByWard($q, $wardNo)   { return $q->where('ward_no', $wardNo); }
}

// ══════════════════════════════════
// ComplaintPhoto
// ══════════════════════════════════
class ComplaintPhoto extends Model {
    protected $fillable = ['complaint_id','photo_path'];

    public function complaint() { return $this->belongsTo(Complaint::class); }
    public function getUrlAttribute() { return asset('storage/'.$this->photo_path); }
}

// ══════════════════════════════════
// Event
// ══════════════════════════════════
class MunicipalEvent extends Model {
    protected $table    = 'events';
    protected $fillable = [
        'title','description','type','ward_no','venue',
        'starts_at','ends_at','banner','is_active','created_by',
    ];
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_active' => 'boolean',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeUpcoming($q) { return $q->where('starts_at', '>=', now())->orderBy('starts_at'); }
    public function scopeActive($q)   { return $q->where('is_active', true); }
}

// ══════════════════════════════════
// Feedback
// ══════════════════════════════════
class Feedback extends Model {
    protected $fillable = ['user_id','rating','message','category'];

    public function user() { return $this->belongsTo(User::class); }
}

// ══════════════════════════════════
// NotificationLog
// ══════════════════════════════════
class NotificationLog extends Model {
    protected $fillable = ['title','body','type','target_wards','sent_count','sent_by'];
    protected $casts    = ['target_wards' => 'array'];

    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
