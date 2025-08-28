<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingOrganizationColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add missing columns to clinique_profiles
        if (Schema::hasTable('clinique_profiles')) {
            Schema::table('clinique_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('clinique_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('clinique_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('services');
                }
                if (!Schema::hasColumn('clinique_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable()->after('profile_image');
                }
                if (!Schema::hasColumn('clinique_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('etablissement_image');
                }
                if (!Schema::hasColumn('clinique_profiles', 'description')) {
                    $table->text('description')->nullable()->after('rating');
                }
                if (!Schema::hasColumn('clinique_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('description');
                }
                if (!Schema::hasColumn('clinique_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('clinique_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('clinique_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('clinique_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('clinique_profiles', 'gallery')) {
                    $table->json('gallery')->nullable()->after('vacation_auto_reactivate_date');
                }
                // Rename gerant_name to responsable_name if it exists
                if (Schema::hasColumn('clinique_profiles', 'gerant_name') && !Schema::hasColumn('clinique_profiles', 'responsable_name')) {
                    $table->renameColumn('gerant_name', 'responsable_name');
                }
            });
        }

        // Add missing columns to pharmacie_profiles
        if (Schema::hasTable('pharmacie_profiles')) {
            Schema::table('pharmacie_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('pharmacie_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'services')) {
                    $table->json('services')->nullable()->after('ville');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('services');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable()->after('profile_image');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('etablissement_image');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'description')) {
                    $table->text('description')->nullable()->after('rating');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('description');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'gallery')) {
                    $table->json('gallery')->nullable()->after('vacation_auto_reactivate_date');
                }
                // Rename gerant_name to responsable_name if it exists
                if (Schema::hasColumn('pharmacie_profiles', 'gerant_name') && !Schema::hasColumn('pharmacie_profiles', 'responsable_name')) {
                    $table->renameColumn('gerant_name', 'responsable_name');
                }
            });
        }

        // Add missing columns to labo_analyse_profiles
        if (Schema::hasTable('labo_analyse_profiles')) {
            Schema::table('labo_analyse_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('labo_analyse_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('services');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable()->after('profile_image');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('etablissement_image');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'description')) {
                    $table->text('description')->nullable()->after('rating');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('description');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'gallery')) {
                    $table->json('gallery')->nullable()->after('vacation_auto_reactivate_date');
                }
                // Rename gerant_name to responsable_name if it exists
                if (Schema::hasColumn('labo_analyse_profiles', 'gerant_name') && !Schema::hasColumn('labo_analyse_profiles', 'responsable_name')) {
                    $table->renameColumn('gerant_name', 'responsable_name');
                }
            });
        }

        // Add missing columns to centre_radiologie_profiles
        if (Schema::hasTable('centre_radiologie_profiles')) {
            Schema::table('centre_radiologie_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('centre_radiologie_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('services');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable()->after('profile_image');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('etablissement_image');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'description')) {
                    $table->text('description')->nullable()->after('rating');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('description');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('centre_radiologie_profiles', 'gallery')) {
                    $table->json('gallery')->nullable()->after('vacation_auto_reactivate_date');
                }
                // Rename gerant_name to responsable_name if it exists
                if (Schema::hasColumn('centre_radiologie_profiles', 'gerant_name') && !Schema::hasColumn('centre_radiologie_profiles', 'responsable_name')) {
                    $table->renameColumn('gerant_name', 'responsable_name');
                }
            });
        }

        // Add missing columns to parapharmacie_profiles
        if (Schema::hasTable('parapharmacie_profiles')) {
            Schema::table('parapharmacie_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('parapharmacie_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'services')) {
                    $table->json('services')->nullable()->after('ville');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('services');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable()->after('profile_image');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('etablissement_image');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'description')) {
                    $table->text('description')->nullable()->after('rating');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('description');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('parapharmacie_profiles', 'gallery')) {
                    $table->json('gallery')->nullable()->after('vacation_auto_reactivate_date');
                }
                // Rename gerant_name to responsable_name if it exists
                if (Schema::hasColumn('parapharmacie_profiles', 'gerant_name') && !Schema::hasColumn('parapharmacie_profiles', 'responsable_name')) {
                    $table->renameColumn('gerant_name', 'responsable_name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'clinique_profiles',
            'pharmacie_profiles', 
            'labo_analyse_profiles',
            'centre_radiologie_profiles',
            'parapharmacie_profiles'
        ];

        $columns = [
            'ville', 'profile_image', 'etablissement_image', 'rating',
            'description', 'org_presentation', 'services_description',
            'additional_info', 'vacation_mode', 'vacation_auto_reactivate_date', 'gallery'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($columns) {
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table->getTable(), $column)) {
                            $table->dropColumn($column);
                        }
                    }
                    // Rename back responsable_name to gerant_name
                    if (Schema::hasColumn($table->getTable(), 'responsable_name')) {
                        $table->renameColumn('responsable_name', 'gerant_name');
                    }
                });
            }
        }
    }
}
