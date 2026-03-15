<?php
// ─────────────────────────────────────────────
//  Kachankawal Rural Municipality – All Migrations
//  Run: php artisan migrate
// ─────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════
// 1. USERS
// ══════════════════════════════════
class CreateUsersTable extends Migration {
    public function up() {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->tinyInteger('ward_no')->nullable()->unsigned();   // 1–7
            $table->string('citizenship_no', 50)->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('role', ['citizen', 'ward_admin', 'municipality_admin'])->default('citizen');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('device_token')->nullable();               // FCM push token
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down() { Schema::dropIfExists('users'); }
}

// ══════════════════════════════════
// 2. OTP TOKENS
// ══════════════════════════════════
class CreateOtpTokensTable extends Migration {
    public function up() {
        Schema::create('otp_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('otp', 10);
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('otp_tokens'); }
}

// ══════════════════════════════════
// 3. WARDS
// ══════════════════════════════════
class CreateWardsTable extends Migration {
    public function up() {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('ward_no')->unsigned()->unique();
            $table->string('ward_name');
            $table->string('secretary_name')->nullable();
            $table->string('secretary_phone', 20)->nullable();
            $table->string('secretary_email')->nullable();
            $table->text('address')->nullable();
            $table->string('office_photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('wards'); }
}

// ══════════════════════════════════
// 4. ELECTED REPRESENTATIVES
// ══════════════════════════════════
class CreateRepresentativesTable extends Migration {
    public function up() {
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('position', ['mayor','deputy_mayor','ward_chairperson','ward_member','women_member']);
            $table->tinyInteger('ward_no')->unsigned()->nullable();
            $table->string('party')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('photo')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('representatives'); }
}

// ══════════════════════════════════
// 5. NOTICES & ANNOUNCEMENTS
// ══════════════════════════════════
class CreateNoticesTable extends Migration {
    public function up() {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body');
            $table->enum('type', ['notice','tender','emergency','meeting','ward_notice'])->default('notice');
            $table->tinyInteger('ward_no')->unsigned()->nullable();   // null = municipality-wide
            $table->string('attachment')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('push_sent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down() { Schema::dropIfExists('notices'); }
}

// ══════════════════════════════════
// 6. SERVICE REQUESTS (digital services)
// ══════════════════════════════════
class CreateServiceRequestsTable extends Migration {
    public function up() {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no', 20)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('service_type', [
                'birth_registration',
                'death_registration',
                'marriage_registration',
                'migration_certificate',
                'recommendation_letter',
                'business_registration',
            ]);
            $table->tinyInteger('ward_no')->unsigned();
            $table->json('form_data');                               // flexible per service
            $table->enum('status', ['pending','processing','approved','rejected','completed'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down() { Schema::dropIfExists('service_requests'); }
}

// ══════════════════════════════════
// 7. SERVICE DOCUMENTS
// ══════════════════════════════════
class CreateServiceDocumentsTable extends Migration {
    public function up() {
        Schema::create('service_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->onDelete('cascade');
            $table->string('document_type');  // e.g. citizenship, photo
            $table->string('file_path');
            $table->string('original_name');
            $table->bigInteger('file_size')->unsigned();
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('service_documents'); }
}

// ══════════════════════════════════
// 8. COMPLAINTS
// ══════════════════════════════════
class CreateComplaintsTable extends Migration {
    public function up() {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no', 20)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('category', [
                'road','water_supply','electricity','sanitation',
                'public_service','corruption','environment','other',
            ]);
            $table->string('title');
            $table->longText('description');
            $table->tinyInteger('ward_no')->unsigned();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_description')->nullable();
            $table->enum('status', ['submitted','under_review','in_progress','resolved','closed'])->default('submitted');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down() { Schema::dropIfExists('complaints'); }
}

// ══════════════════════════════════
// 9. COMPLAINT PHOTOS
// ══════════════════════════════════
class CreateComplaintPhotosTable extends Migration {
    public function up() {
        Schema::create('complaint_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->onDelete('cascade');
            $table->string('photo_path');
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('complaint_photos'); }
}

// ══════════════════════════════════
// 10. EVENTS
// ══════════════════════════════════
class CreateEventsTable extends Migration {
    public function up() {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->enum('type', ['program','community','awareness','vaccination','other'])->default('program');
            $table->tinyInteger('ward_no')->unsigned()->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('banner')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('events'); }
}

// ══════════════════════════════════
// 11. FEEDBACK
// ══════════════════════════════════
class CreateFeedbackTable extends Migration {
    public function up() {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->text('message')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('feedback'); }
}

// ══════════════════════════════════
// 12. PUSH NOTIFICATION LOG
// ══════════════════════════════════
class CreateNotificationLogsTable extends Migration {
    public function up() {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();
            $table->json('target_wards')->nullable(); // null = all wards
            $table->integer('sent_count')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('notification_logs'); }
}
