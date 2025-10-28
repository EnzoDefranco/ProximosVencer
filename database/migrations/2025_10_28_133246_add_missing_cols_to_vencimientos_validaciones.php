<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Solo si existe la tabla
        if (Schema::hasTable('vencimientos_validaciones')) {
            Schema::table('vencimientos_validaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('vencimientos_validaciones', 'item_id')) {
                    $table->unsignedBigInteger('item_id')->unique()->after('id');
                } else {
                    // aseguro unicidad
                    $table->unique('item_id');
                }

                if (!Schema::hasColumn('vencimientos_validaciones', 'checked')) {
                    $table->boolean('checked')->default(false)->after('item_id');
                }

                if (!Schema::hasColumn('vencimientos_validaciones', 'checked_by')) {
                    $table->unsignedBigInteger('checked_by')->nullable()->after('checked');
                }

                if (!Schema::hasColumn('vencimientos_validaciones', 'checked_at')) {
                    $table->timestamp('checked_at')->nullable()->after('checked_by');
                }

                if (!Schema::hasColumn('vencimientos_validaciones', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('vencimientos_validaciones', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // opcional: no elimines columnas para no perder datos
    }
};
