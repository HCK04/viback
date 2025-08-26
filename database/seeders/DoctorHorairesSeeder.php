<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\MedecinProfile;
use App\Models\KineProfile;
use App\Models\PsychologueProfile;
use App\Models\Role;

class DoctorHorairesSeeder extends Seeder
{
    public function run()
    {
        // Get or create roles
        $medecinRole = Role::firstOrCreate(['name' => 'medecin']);
        $kineRole = Role::firstOrCreate(['name' => 'kine']);
        $psychoRole = Role::firstOrCreate(['name' => 'psychologue']);

        // Create sample doctors with different schedules
        $doctors = [
            [
                'name' => 'Dr. Sarah Martin',
                'email' => 'sarah.martin@example.com',
                'role_id' => $medecinRole->id,
                'specialty' => 'Cardiologie',
                'horaires' => '08:00-17:00', // 8 AM to 5 PM
                'experience_years' => 10,
                'adresse' => '123 Rue de la Santé, Paris'
            ],
            [
                'name' => 'Dr. Ahmed Benali',
                'email' => 'ahmed.benali@example.com',
                'role_id' => $medecinRole->id,
                'specialty' => 'Pédiatrie',
                'horaires' => '09:00-18:00', // 9 AM to 6 PM
                'experience_years' => 8,
                'adresse' => '456 Avenue des Enfants, Lyon'
            ],
            [
                'name' => 'Dr. Marie Dubois',
                'email' => 'marie.dubois@example.com',
                'role_id' => $medecinRole->id,
                'specialty' => 'Dermatologie',
                'horaires' => '10:00-16:00', // 10 AM to 4 PM (shorter day)
                'experience_years' => 15,
                'adresse' => '789 Boulevard de la Peau, Marseille'
            ],
            [
                'name' => 'Dr. Jean Moreau',
                'email' => 'jean.moreau@example.com',
                'role_id' => $medecinRole->id,
                'specialty' => 'Urgence',
                'horaires' => '07:00-19:00', // 7 AM to 7 PM (long day)
                'experience_years' => 12,
                'adresse' => 'Hôpital Central, Toulouse'
            ],
            [
                'name' => 'Kinésithérapeute Paul Leroy',
                'email' => 'paul.leroy@example.com',
                'role_id' => $kineRole->id,
                'specialty' => 'Kinésithérapie',
                'horaires' => '08:30-17:30', // 8:30 AM to 5:30 PM
                'experience_years' => 6,
                'adresse' => '321 Rue du Sport, Nice'
            ],
            [
                'name' => 'Psychologue Claire Rousseau',
                'email' => 'claire.rousseau@example.com',
                'role_id' => $psychoRole->id,
                'specialty' => 'Psychologie',
                'horaires' => '09:00-17:00', // 9 AM to 5 PM
                'experience_years' => 9,
                'adresse' => '654 Avenue de l\'Esprit, Bordeaux'
            ]
        ];

        foreach ($doctors as $doctorData) {
            // Create user
            $user = User::firstOrCreate(
                ['email' => $doctorData['email']],
                [
                    'name' => $doctorData['name'],
                    'email' => $doctorData['email'],
                    'password' => bcrypt('password123'),
                    'role_id' => $doctorData['role_id'],
                    'email_verified_at' => now()
                ]
            );

            // Create profile based on role
            if ($doctorData['role_id'] == $medecinRole->id) {
                MedecinProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'specialty' => $doctorData['specialty'],
                        'horaires' => $doctorData['horaires'],
                        'experience_years' => $doctorData['experience_years'],
                        'adresse' => $doctorData['adresse'],
                        'disponible' => true
                    ]
                );
            } elseif ($doctorData['role_id'] == $kineRole->id) {
                KineProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'specialty' => $doctorData['specialty'],
                        'horaires' => $doctorData['horaires'],
                        'experience_years' => $doctorData['experience_years'],
                        'adresse' => $doctorData['adresse'],
                        'disponible' => true
                    ]
                );
            } elseif ($doctorData['role_id'] == $psychoRole->id) {
                PsychologueProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'specialty' => $doctorData['specialty'],
                        'horaires' => $doctorData['horaires'],
                        'experience_years' => $doctorData['experience_years'],
                        'adresse' => $doctorData['adresse'],
                        'disponible' => true
                    ]
                );
            }

            echo "Created doctor: {$doctorData['name']} with schedule: {$doctorData['horaires']}\n";
        }
    }
}
