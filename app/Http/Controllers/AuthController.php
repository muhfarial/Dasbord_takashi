<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Database;
use PDOException;

/**
 * Takashi Fried Chicken (TFC) - RBAC Authentication Controller
 * Uses PHP password_verify() with bcrypt hashes & PDO MySQL lookup (single source of truth for Palu outlets).
 */
class AuthController
{
    /**
     * Real bcrypt hash for default demo password "password"
     * Generated via password_hash('password', PASSWORD_BCRYPT)
     */
    private const DEFAULT_BCRYPT_HASH = '$2y$10$67JrtPeqUfsk/iJ7.M9WVOb1DzxIaKWmHGSx7//PXxonYOjTRiGLa';

    private static array $users = [
        [
            'id' => 'usr-01',
            'name' => 'Sarah Wijaya, S.E.',
            'email' => 'owner@takashi.id',
            'password_hash' => self::DEFAULT_BCRYPT_HASH,
            'role' => 'OWNER',
            'outlet_id' => 'out-01',
            'badge' => 'HQ Owner / Executive',
            'defaultScreen' => 'SCR-02',
        ],
        [
            'id' => 'usr-02',
            'name' => 'Bima Sakti',
            'email' => 'hq@takashi.id',
            'password_hash' => self::DEFAULT_BCRYPT_HASH,
            'role' => 'HQ_SUPPLY_CHAIN',
            'outlet_id' => 'out-01',
            'badge' => 'Head of Supply Chain',
            'defaultScreen' => 'SCR-02',
        ],
        [
            'id' => 'usr-03',
            'name' => 'Chef Danu Prasetyo',
            'email' => 'dayodara@takashi.id',
            'password_hash' => self::DEFAULT_BCRYPT_HASH,
            'role' => 'STORE_MANAGER',
            'outlet_id' => 'out-02',
            'badge' => 'Store Manager DayoDara',
            'defaultScreen' => 'SCR-03',
        ],
        [
            'id' => 'usr-04',
            'name' => 'Agus Koki',
            'email' => 'kitchen.dayodara@takashi.id',
            'password_hash' => self::DEFAULT_BCRYPT_HASH,
            'role' => 'KITCHEN_STAFF',
            'outlet_id' => 'out-02',
            'badge' => 'Kitchen Staff DayoDara',
            'defaultScreen' => 'SCR-05',
        ],
    ];

    private function roleMeta(string $role): array
    {
        return match ($role) {
            'OWNER'           => ['badge' => 'HQ Owner / Executive', 'defaultScreen' => 'SCR-02'],
            'HQ_SUPPLY_CHAIN' => ['badge' => 'Head of Supply Chain', 'defaultScreen' => 'SCR-02'],
            'STORE_MANAGER'   => ['badge' => 'Store Manager DayoDara', 'defaultScreen' => 'SCR-03'],
            'KITCHEN_STAFF'   => ['badge' => 'Kitchen Staff DayoDara', 'defaultScreen' => 'SCR-05'],
            default           => ['badge' => 'Staff Takashi', 'defaultScreen' => 'SCR-03'],
        };
    }

    public function login(array $request): array
    {
        $email = strtolower(trim((string)($request['email'] ?? '')));
        $password = (string)($request['password'] ?? '');

        if ($email === '' || $password === '') {
            http_response_code(422);
            return [
                'status' => 'error',
                'message' => 'Email dan kata sandi wajib diisi untuk verifikasi.',
            ];
        }

        // 1. Try verifying against MySQL 8.0 users table via PDO first
        $pdo = Database::mysql();
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare('SELECT id, outlet_id, name, email, password, role, is_active FROM users WHERE LOWER(email) = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $row = $stmt->fetch();

                if ($row) {
                    if ((int)($row['is_active'] ?? 1) !== 1) {
                        http_response_code(403);
                        return ['status' => 'error', 'message' => 'Akun ini sedang dinonaktifkan oleh HQ.'];
                    }

                    if (password_verify($password, (string)$row['password'])) {
                        $meta = $this->roleMeta((string)$row['role']);
                        return [
                            'status' => 'success',
                            'authSource' => 'MySQL PDO (bcrypt verified)',
                            'token' => 'tfc_token_' . bin2hex(random_bytes(16)),
                            'user' => [
                                'id' => $row['id'],
                                'name' => $row['name'],
                                'email' => $row['email'],
                                'role' => $row['role'],
                                'outlet_id' => $row['outlet_id'],
                                'badge' => $meta['badge'],
                                'defaultScreen' => $meta['defaultScreen'],
                            ],
                            'message' => 'Autentikasi MySQL berhasil sebagai ' . $row['name'],
                        ];
                    }

                    http_response_code(401);
                    return [
                        'status' => 'error',
                        'message' => 'Kata sandi yang Anda masukkan salah. Gunakan "password" untuk akun demo.',
                    ];
                }
            } catch (PDOException $e) {
                // Fall through to verified static bcrypt directory if users query fails
            }
        }

        // 2. Fallback verified bcrypt user directory (single source of truth for Palu outlets)
        foreach (self::$users as $u) {
            if (strtolower($u['email']) === $email) {
                if (password_verify($password, $u['password_hash'])) {
                    return [
                        'status' => 'success',
                        'authSource' => 'PHP password_verify() (bcrypt verified)',
                        'token' => 'tfc_token_' . bin2hex(random_bytes(16)),
                        'user' => [
                            'id' => $u['id'],
                            'name' => $u['name'],
                            'email' => $u['email'],
                            'role' => $u['role'],
                            'outlet_id' => $u['outlet_id'],
                            'badge' => $u['badge'],
                            'defaultScreen' => $u['defaultScreen'],
                        ],
                        'message' => 'Autentikasi berhasil sebagai ' . $u['name'],
                    ];
                }

                http_response_code(401);
                return [
                    'status' => 'error',
                    'message' => 'Kata sandi yang Anda masukkan salah. Gunakan "password" untuk akun demo.',
                ];
            }
        }

        http_response_code(401);
        return [
            'status' => 'error',
            'message' => 'Email tidak terdaftar dalam sistem Takashi Fried Chicken Palu.',
        ];
    }

    public function logout(): array
    {
        return [
            'status' => 'success',
            'message' => 'Sesi berhasil diakhiri. Akses dasbor terkunci.',
        ];
    }
}
