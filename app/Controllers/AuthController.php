<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;


use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;


class AuthController extends CoreController
{
    protected $format = 'json';


    public function get_all_users()
    {
        $db = \Config\Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit 10
        $page = $this->request->getVar('page') ?? 1; // Default page 1
        $search = $this->request->getVar('search');
        $filter = $this->request->getVar('filter');

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        try {
            // Query dasar tanpa mengikutkan password
            $query = "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address FROM admin";
            $conditions = [];
            $params = [];

            // Tambahkan filter dan pencarian jika ada
            if ($search) {
                $conditions[] = "(admin_username LIKE ? OR admin_full_name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($filter) {
                $conditions[] = "admin_role = ?";
                $params[] = $filter;
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Tambahkan limit dan offset untuk pagination
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            $users = $db->query($query, $params)->getResultArray();


            // Hitung total pengguna untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM admin";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('Users retrieved successfully.', [
                'data' => $users,
                'total' => $total,
                'limit' => (int) $limit,
                'page' => (int) $page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve users: ' . $e->getMessage());
        }
    }





    public function get_user_by_id($admin_id)
    {
        $db = \Config\Database::connect();

        try {
            $query = "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address  FROM admin WHERE admin_id = ?";
            $user = $db->query($query, [$admin_id])->getRowArray();

            $data = [
                'id' => $user['admin_id'],
                'username' => $user['admin_username'],
                'email' => $user['admin_email'],
                'full_name' => $user['admin_full_name'],
                'nik' => $user['admin_nik'],
                'role' => $user['admin_role'],
                'phone' => $user['admin_phone'],
                'gender' => $user['admin_gender'],
                'address' => $user['admin_address'],


            ];

            if (!$user) {
                return $this->respondWithNotFound('User not found.');
            }

            return $this->respondWithSuccess('User found.', $data);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve user: ' . $e->getMessage());
        }
    }



    public function delete_account($admin_id)
    {
        $db = \Config\Database::connect();

        try {
            // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
            $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
            $user = $db->query($query, [$admin_id])->getRowArray();

            if (!$user) {
                return $this->respondWithError('User not found.');
            }

            if ($user['admin_role'] !== 'warehouse' && $user['admin_role'] !== 'frontliner') {
                return $this->respondWithUnauthorized('Only warehouse and frontliner users can be deleted.');
            }

            // Lakukan penghapusan data
            $deleteQuery = "DELETE FROM admin WHERE admin_id = ?";
            $db->query($deleteQuery, [$admin_id]);

            return $this->respondWithSuccess('Account deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Deletion failed: ' . $e->getMessage());
        }
    }

    // Fungsi untuk edit account (hanya warehouse dan frontliner)
    public function edit_account($admin_id)
    {
        $db = \Config\Database::connect();

        // Aturan validasi data yang akan diubah
        $rules = [
            'username' => 'required|min_length[5]',
            'email' => 'required|valid_email',
            'full_name' => 'required',
            'phone' => 'required|numeric',
            'address' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'admin_username' => $this->request->getVar('username'),
            'admin_email' => $this->request->getVar('email'),
            'admin_full_name' => $this->request->getVar('full_name'),
            'admin_phone' => $this->request->getVar('phone'),
            'admin_address' => $this->request->getVar('address'),
        ];

        try {
            // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
            $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
            $user = $db->query($query, [$admin_id])->getRowArray();

            if (!$user) {
                return $this->respondWithError('User not found.');
            }

            if ($user['admin_role'] !== 'warehouse' && $user['admin_role'] !== 'frontliner') {
                return $this->respondWithUnauthorized('Only warehouse and frontliner users can be edited.');
            }

            // Lakukan update data
            $updateQuery = "UPDATE admin SET admin_username = ?, admin_email = ?, admin_full_name = ?, admin_phone = ?, admin_address = ? WHERE admin_id = ?";
            $db->query($updateQuery, [
                $data['admin_username'],
                $data['admin_email'],
                $data['admin_full_name'],
                $data['admin_phone'],
                $data['admin_address'],
                $admin_id
            ]);

            return $this->respondWithSuccess('Account updated successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Update failed: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendaftarkan pengguna (hanya warehouse dan frontliner)
    public function register()
    {
        $db = \Config\Database::connect();

        // Validasi input
        $rules = [
            'username' => 'required|min_length[5]',
            'password' => 'required|min_length[8]',
            'email' => 'required|valid_email',
            'full_name' => 'required',
            'nik' => 'required|numeric|min_length[16]|max_length[16]',
            'role' => 'required|in_list[warehouse,frontliner]', // Validasi role, fix typo
            'phone' => 'required|numeric',
            'gender' => 'required|in_list[male,female]',
            'address' => 'required'
        ];

        // Validasi input berdasarkan rules
        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Ambil data dari request
        $data = [
            'admin_username' => $this->request->getVar('username'),
            'admin_password' => $this->request->getVar('password'),
            'admin_email' => $this->request->getVar('email'),
            'admin_full_name' => $this->request->getVar('full_name'),
            'admin_nik' => $this->request->getVar('nik'),
            'admin_role' => $this->request->getVar('role'),
            'admin_phone' => $this->request->getVar('phone'),
            'admin_gender' => $this->request->getVar('gender'),
            'admin_address' => $this->request->getVar('address'),
        ];

        try {
            // Hash password sebelum menyimpan ke database
            $data['admin_password'] = password_hash($data['admin_password'], PASSWORD_DEFAULT);

            // Raw query untuk memasukkan data ke tabel admin
            $query = "INSERT INTO admin (admin_username, admin_password, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Eksekusi query
            $db->query($query, [
                $data['admin_username'],
                $data['admin_password'],
                $data['admin_email'],
                $data['admin_full_name'],
                $data['admin_nik'],
                $data['admin_role'],
                $data['admin_phone'],
                $data['admin_gender'],
                $data['admin_address']
            ]);

            return $this->respondWithSuccess('Registration successful.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Registration failed: ' . $e->getMessage());
        }
    }



    public function login()
    {
        $db = \Config\Database::connect();

        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $username = $this->request->getVar('username');
        $password = $this->request->getVar('password');

        try {
            // Query untuk mendapatkan data user berdasarkan username
            $query = "SELECT * FROM admin WHERE admin_username = ?";
            $user = $db->query($query, [$username])->getRowArray();

            if ($user && password_verify($password, $user['admin_password'])) {
                // Buat token random
                $token = bin2hex(random_bytes(32));  // Random string sebagai token

                // Simpan token ke database
                $query = "INSERT INTO admin_token (admin_id, token, expires_at) VALUES (?, ?, ?)";
                $db->query($query, [
                    $user['admin_id'],
                    $token,
                    date('Y-m-d H:i:s', strtotime('+1 hour'))
                ]);

                // Format respons
                $response = [
                    'id' => $user['admin_id'],
                    'username' => $user['admin_username'],
                    'role' => $user['admin_role'],
                    'token' => $token
                ];

                return $this->respondWithSuccess('Login successful.', $response);
            }

            return $this->respondWithUnauthorized('Invalid username or password.');
        } catch (\Exception $e) {
            return $this->respondWithError('Login failed: ' . $e->getMessage());
        }
    }

    // Fungsi logout (hanya contoh)
    public function logout()
    {
        $db = \Config\Database::connect();

        // Ambil token dari request header (Bearer Token)
        $authHeader = $this->request->getHeader(name: 'Authorization');
        $token = null;

        if ($authHeader) {
            $token = str_replace('Bearer ', '', $authHeader->getValue());
        }

        if (!$token) {
            return $this->respondWithValidationError('Token is required.');
        }

        try {
            // Hapus token dari database
            $query = "DELETE FROM admin_token WHERE token = ?";
            $db->query($query, [$token]);

            return $this->respondWithSuccess('Logged out successfully.');
        } catch (\Exception $e) {
            return $this->respondWithError('Logout failed: ' . $e->getMessage());
        }
    }



    // public function resetPassword()
    // {
    //     $db = \Config\Database::connect();

    //     $email = $this->request->getVar('email');

    //     // Validasi input
    //     if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    //         return $this->respondWithValidationError('Invalid email address.');
    //     }

    //     try {
    //         // Cek apakah email tersebut ada di database
    //         $query = "SELECT admin_id, admin_username FROM admin WHERE admin_email = ?";
    //         $user = $db->query($query, [$email])->getRowArray();

    //         if (!$user) {
    //             return $this->respondWithNotFound('Email not found.');
    //         }

    //         // Buat token reset password
    //         $resetToken = bin2hex(random_bytes(32));  // Random token
    //         $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));  // Token berlaku 1 jam

    //         // Simpan token reset password di tabel admin_token
    //         $query = "INSERT INTO admin_token (admin_id, token, expires_at) VALUES (?, ?, ?)";
    //         $db->query($query, [$user['admin_id'], $resetToken, $expiresAt]);

    //         // Kirim email reset password menggunakan SMTP Zoho
    //         $resetLink = base_url("/reset-password?token=$resetToken");

    //         if ($this->sendResetEmail($email, $resetLink)) {
    //             return $this->respondWithSuccess('Password reset link has been sent to your email.');
    //         } else {
    //             return $this->respondWithError('Failed to send reset password email.');
    //         }

    //     } catch (\Exception $e) {
    //         return $this->respondWithError('Reset password failed: ' . $e->getMessage());
    //     }
    // }

    // // Fungsi untuk mengirim email reset password
    // private function sendResetEmail($email, $resetLink)
    // {
    //     $mail = new PHPMailer(true);

    //     try {
    //         // Konfigurasi SMTP Zoho
    //         $mail->isSMTP();
    //         $mail->Host = 'smtp.zoho.com';  // SMTP server Zoho
    //         $mail->SMTPAuth = true;
    //         $mail->Username = 'hello@dzakyabdurhmn.me';  // Ganti dengan email Zoho kamu
    //         $mail->Password = 'e6b7467bd37b2160a5cb4cf9-2ddc603bd48e8f6ce4cb8abfc5273775445ddd16acfc5ea02b0e200f3b1d3c3e3176ded2ec52b122e87de37823de9ce8d77cce3c78523eed5603de5ec6bbdd26a55939b8a8569ce444530d6d94b9ef6b46751a6927acf8fd44c2e68004264d010dfaac5f8426';  // Ganti dengan password Zoho kamu
    //         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    //         $mail->Port = 587;

    //         // Pengaturan email pengirim dan penerima
    //         $mail->setFrom('hello@dzakyabdurhmn.me', 'Your Company');
    //         $mail->addAddress($email);

    //         // Konten email
    //         $mail->isHTML(true);
    //         $mail->Subject = 'Reset Password';
    //         $mail->Body = "Click the following link to reset your password: <a href='$resetLink'>$resetLink</a>";

    //         // Kirim email
    //         return $mail->send();
    //     } catch (Exception $e) {
    //         log_message('error', 'Email could not be sent. Mailer Error: ' . $mail->ErrorInfo);
    //         return false;
    //     }
    // }



    public function sendResetPassword()
    {
        $email = $this->request->getVar('email');
        $db = \Config\Database::connect();

        // Cek apakah email terdaftar
        $query = "SELECT * FROM admin WHERE admin_email = ?";
        $user = $db->query($query, [$email])->getRowArray();

        if (!$user) {
            return $this->respondWithError('Email tidak ditemukan.');
        }

        // Buat token reset password
        $token = bin2hex(random_bytes(32)); // Random string sebagai token
        $expiresAt = date('Y-m-d H:i:s', timestamp: strtotime('+5 hour'));

        // Simpan token ke tabel admin_token
        try {
            $query = "INSERT INTO admin_token (admin_id, token, expires_at) VALUES (?, ?, ?)";
            $db->query($query, [$user['admin_id'], $token, $expiresAt]);
        } catch (\Exception $e) {
            return $this->respondWithError('Gagal menyimpan token: ' . $e->getMessage());
        }

        // Kirim email menggunakan Mailtrap
        $apiKey = '4007328c927d8cb584af65af63467184'; // Ganti dengan API Key Mailtrap kamu
        $mailtrap = MailtrapClient::initSendingEmails(
            apiKey: $apiKey,
        );

        $resetLink = base_url('password/reset?token=' . $token); // Link reset password

        // Buat email
        $emailMessage = (new MailtrapEmail())
            ->from(new Address('hello@demomailtrap.com', 'Admin Perpustakaan Jogja')) // Ganti dengan alamat pengirim
            ->to(new Address($email)) // Kirim ke alamat email pengguna
            ->subject('Reset Password Anda')
            ->text("Halo,\n\nKami menerima permintaan untuk mereset password akun Anda. Silakan klik tautan berikut untuk mereset password Anda: $resetLink\n\nTautan ini hanya berlaku selama 1 jam.\n\nSalam,\nAdmin Perpustakaan Jogja")
            ->category('Password Reset');

        try {
            // Kirim email
            $response = $mailtrap->send($emailMessage);
            $messageIds = ResponseHelper::toArray($response)['message_ids'];

            return $this->respondWithSuccess('Link reset password telah dikirim ke email Anda.', [
                'message_ids' => $messageIds
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('Gagal mengirim email: ' . $e->getMessage());
        }
    }

    public function resetPassword()
    {
        $db = \Config\Database::connect();
        $token = $this->request->getVar('token');
        $newPassword = $this->request->getVar('new_password');

        // Validasi input
        $rules = [
            'token' => 'required',
            'new_password' => 'required|min_length[8]'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Cari token di database dan cek masa berlaku
        $query = "SELECT * FROM admin_token WHERE token = ? AND expires_at > NOW()";
        $tokenData = $db->query($query, [$token])->getRowArray();

        if (!$tokenData) {
            return $this->respondWithUnauthorized('Token tidak valid atau sudah kadaluarsa.');
        }

        // Hash password baru
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password di tabel admin
        try {
            $query = "UPDATE admin SET admin_password = ? WHERE admin_id = ?";
            $db->query($query, [$hashedPassword, $tokenData['admin_id']]);
        } catch (\Exception $e) {
            return $this->respondWithError('Gagal memperbarui password: ' . $e->getMessage());
        }

        // Hapus token setelah digunakan
        try {
            $query = "DELETE FROM admin_token WHERE token = ?";
            $db->query($query, [$token]);
        } catch (\Exception $e) {
            return $this->respondWithError('Gagal menghapus token: ' . $e->getMessage());
        }

        return $this->respondWithSuccess('Password berhasil direset.');
    }



}



