<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('student_applications')) {
            return;
        }

        Schema::table('student_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_applications', 'rule_score')) {
                $table->decimal('rule_score', 8, 4)->nullable()->after('review_priority');
            }

            if (! Schema::hasColumn('student_applications', 'rule_recommendation')) {
                $table->string('rule_recommendation', 30)->nullable()->after('rule_score');
            }

            if (! Schema::hasColumn('student_applications', 'document_submission_link')) {
                $table->string('document_submission_link', 2048)->nullable()->after('rule_recommendation');
            }

            if (! Schema::hasColumn('student_applications', 'supporting_document_url')) {
                $table->string('supporting_document_url', 2048)->nullable()->after('document_submission_link');
            }

            if (! Schema::hasColumn('student_applications', 'supporting_document_path')) {
                $table->string('supporting_document_path', 512)->nullable()->after('supporting_document_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('student_applications')) {
            return;
        }

        Schema::table('student_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('student_applications', 'supporting_document_path')) {
                $table->dropColumn('supporting_document_path');
            }

            if (Schema::hasColumn('student_applications', 'supporting_document_url')) {
                $table->dropColumn('supporting_document_url');
            }

            if (Schema::hasColumn('student_applications', 'document_submission_link')) {
                $table->dropColumn('document_submission_link');
            }

            if (Schema::hasColumn('student_applications', 'rule_recommendation')) {
                $table->dropColumn('rule_recommendation');
            }

            if (Schema::hasColumn('student_applications', 'rule_score')) {
                $table->dropColumn('rule_score');
            }
        });
    }
};
