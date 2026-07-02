<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Membres opérationnels rattachés à l'organisation par défaut.
     *
     * Le mot de passe est déjà hashé (bcrypt) : le cast "hashed" détecte les
     * valeurs déjà hashées et ne les re-hashe pas.
     *
     * @var list<array{name: string, email: string, password: string, department: string}>
     */
    private const USERS = [
        [
            'name' => 'Ilyass Moustaadil',
            'email' => 'ilyass.moustaadil@laprophan.com',
            'password' => '$2y$12$OQ.EmNddTYDMMHlkx9TNcOpaTNYlSuPBw9TaU3UlTc68puZKRHw9C',
            'department' => 'Formes sèches',
        ],
        [
            'name' => 'Ali Hassine',
            'email' => 'ali.hassine@laprophan.com',
            'password' => '$2y$12$/NWukxl.liWHSdygURY2G.1W0WVoqtohH5DvLApkv1C2uzdvC4TYq',
            'department' => 'Formes liquides & pateuses',
        ],
        [
            'name' => 'Noureddine Belkacemi',
            'email' => 'noureddine.belkacemi@laprophan.com',
            'password' => '$2y$12$XM2Y5ZunvOtdPYryXmafGOg1Ba2dPrGnR6QEJ5K7LlyRb3ozeTtgu',
            'department' => 'Formes stériles',
        ],
        [
            'name' => 'Imad Khoumri',
            'email' => 'imad.khoumri@laprophan.com',
            'password' => '$2y$12$TWWU16xzgZl49zGZVkHURO0kef7oKU3QojMyWlklyNcGvduXdSqr6',
            'department' => 'Maintenance process',
        ],
        [
            'name' => 'Hamza Zaki',
            'email' => 'hamza.zaki@laprophan.com',
            'password' => '$2y$12$gVhYwy21Z6FhWL/MASJc..zH/qYJxrzhg0vJ4JGj.mZisOqrhrGOO',
            'department' => 'Utilités',
        ],
        [
            'name' => 'Abdelkader Eloutati',
            'email' => 'abdelkader.eloutati@laprophan.com',
            'password' => '$2y$12$ksrHqvtN26lJb6jy/TtRAukJCgSz0X88fDhLGUt9uCyxYW6xomeT.',
            'department' => 'Moyens généraux',
        ],
        [
            'name' => 'Mehdi Bennani',
            'email' => 'mehdi.bennani@laprophan.com',
            'password' => '$2y$12$XnjJx64g/BvB8g7kJV460Oib0GwoyOBC0LGm9HcxSrWtYjAhlmukm',
            'department' => 'Ordonnancement',
        ],
        [
            'name' => 'Youssef Bakkass',
            'email' => 'youssef.bakkass@laprophan.com',
            'password' => '$2y$12$fSo4duW24TXD1iAh7TpE7.AHJQH7W3So6oR/sAJYh2uAj2qnLVMDK',
            'department' => 'Performances industrielle',
        ],
        [
            'name' => 'Kaoutar Akrikar',
            'email' => 'kaoutar.akrikar@laprophan.com',
            'password' => '$2y$12$qiC2i5PlDOLgl6QQ.wov5uitUOQ.D2zRfJ5jL18COSlQGnNcrRcF.',
            'department' => 'Gestion resources',
        ],
    ];

    public function run(): void
    {
        $organization = Organization::query()->orderBy('id')->first();

        if ($organization === null) {
            return;
        }

        // Bind the org so tenant-scoped lookups (roles, departments) resolve to it.
        app()->instance('currentOrganizationId', $organization->id);

        $role = Role::query()->where('name', 'Utilisateur')->first();

        $departments = Department::query()
            ->where('organization_id', $organization->id)
            ->pluck('id', 'name');

        foreach (self::USERS as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name'], 'password' => $row['password']],
            );

            $user->organizations()->syncWithoutDetaching([
                $organization->id => ['role_id' => $role?->id],
            ]);

            $departmentId = $departments->get($row['department']);

            if ($departmentId !== null) {
                $user->departments()->syncWithoutDetaching([$departmentId]);
            }
        }
    }
}
