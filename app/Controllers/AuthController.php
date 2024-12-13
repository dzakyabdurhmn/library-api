<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

$pager = service("pager");

class AuthController extends AuthorizationController
{
  protected $format = "json";

  public function get_all_users()
  {
    $tokenValidation = $this->validateToken("superadmin");

    if ($tokenValidation !== true) {
      return $this->respond($tokenValidation, $tokenValidation["status"]);
    }

    $limit = $this->request->getVar("limit") ?? 10;
    $page = $this->request->getVar("page") ?? 1;
    $search = $this->request->getVar(index: "search");
    $sort = $this->request->getVar(index: "sort") ?? "";
    $filters = $this->request->getVar("filter") ?? [];
    $enablePagination =
      filter_var(
        $this->request->getVar("pagination"),
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
      ) ?? true;

    $offset = ($page - 1) * $limit;

    $query =
      "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address FROM admin";
    $conditions = [];
    $params = [];

    if ($search) {
      $conditions[] =
        "(admin_username LIKE ? OR admin_full_name LIKE ? OR admin_email LIKE ? OR admin_nik LIKE ? OR admin_phone LIKE ? OR admin_address LIKE ? OR admin_gender LIKE ?)";
      $searchTerm = "%" . $search . "%";
      $params = array_fill(0, 7, $searchTerm);
    }

    $filterMapping = [
      "id" => "admin_id",
      "username" => "admin_username",
      "email" => "admin_email",
      "full_name" => "admin_full_name",
      "nik" => "admin_nik",
      "role" => "admin_role",
      "phone" => "admin_phone",
      "gender" => "admin_gender",
      "address" => "admin_address",
    ];

    if (!empty($sort)) {
      $sortField = ltrim($sort, "-");
      $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
      if (array_key_exists($sortField, $filterMapping)) {
        $query .= " ORDER BY {$filterMapping[$sortField]} $sortDirection";
      }
    }

    foreach ($filters as $key => $value) {
      if (!empty($value) && array_key_exists($key, $filterMapping)) {
        $conditions[] = "{$filterMapping[$key]} = ?";
        $params[] = $value;
      }
    }

    if (count($conditions) > 0) {
      $query .= " WHERE " . implode(" AND ", $conditions);
    }

    if ($enablePagination) {
      $query .= " LIMIT ? OFFSET ?";
      $params[] = (int) $limit;
      $params[] = (int) $offset;
    }

    try {
      $users = $this->db->query($query, $params)->getResultArray();

      $result = [];
      foreach ($users as $user) {
        $result[] = [
          "id" => (int) $user["admin_id"],
          "username" => $user["admin_username"],
          "email" => $user["admin_email"],
          "full_name" => $user["admin_full_name"],
          "nik" => $user["admin_nik"],
          "role" => $user["admin_role"],
          "phone" => $user["admin_phone"],
          "gender" => $user["admin_gender"],
          "address" => $user["admin_address"],
        ];
      }

      $pagination = new \stdClass();
      if ($enablePagination) {
        $totalQuery = "SELECT COUNT(*) as total FROM admin";
        if (!empty($conditions)) {
          $totalQuery .= " WHERE " . implode(" AND ", $conditions);
        }
        $total = $this->db
          ->query(
            $totalQuery,
            array_slice($params, 0, count($params) - 2)
          )
          ->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $pagination = [
          "total_data" => (int) $total,
          "jumlah_page" => (int) $jumlah_page,
          "prev" => $page > 1 ? $page - 1 : null,
          "page" => (int) $page,
          "next" => $page < $jumlah_page ? $page + 1 : null,
          "start" => ($page - 1) * $limit + 1,
          "end" => min($page * $limit, $total),
          "detail" => range(
            max(1, $page - 2),
            min($jumlah_page, $page + 2)
          ),
        ];
      }

      return $this->respondWithSuccess(
        "Berhasil mendapatkan data karyawan",
        [
          "data" => $result,
          "pagination" => $pagination,
        ]
      );
    } catch (DatabaseException $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di server: " . $e->getMessage()
      );
    }
  }

  public function get_user_by_id()
  {
    $admin_id = $this->request->getVar(index: "id");

    $tokenValidation = $this->validateToken("superadmin"); // Fungsi helper dipanggil

    if ($tokenValidation !== true) {
      return $this->respond($tokenValidation, $tokenValidation["status"]);
    }

    try {
      $query =
        "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address  FROM admin WHERE admin_id = ?";
      $user = $this->db->query($query, [$admin_id])->getRowArray();

      $data = [];

      if (!$user) {
        return $this->respondWithSuccess("Data tidak tersedia.", $data);
      }

      $data = [
        "data" => [
          "id" => (int) $user["admin_id"],
          "username" => $user["admin_username"],
          "email" => $user["admin_email"],
          "full_name" => $user["admin_full_name"],
          "nik" => $user["admin_nik"],
          "role" => $user["admin_role"],
          "phone" => $user["admin_phone"],
          "gender" => $user["admin_gender"],
          "address" => $user["admin_address"],
        ],
      ];

      return $this->respondWithSuccess(
        "Data karyawan di temukan.",
        $data
      );
    } catch (DatabaseException $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di sisi server: " . $e->getMessage()
      );
    }
  }

  public function delete_account()
  {
    $admin_id = $this->request->getVar("id");

    try {
      // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
      $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
      $user = $this->db->query($query, [$admin_id])->getRowArray();

      if (!$user) {
        throw new \Exception("Error Processing Request", 1);
      }

      if (
        $user["admin_role"] !== "warehouse" &&
        $user["admin_role"] !== "frontliner"
      ) {
        return $this->respondWithUnauthorized(
          "Hanya wearhouse dan frontliner yang dapat membuat akun."
        );
      }

      // Lakukan penghapusan data
      $deleteQuery = "DELETE FROM admin WHERE admin_id = ?";
      $this->db->query($deleteQuery, [$admin_id]);

      return $this->respondWithSuccess(
        "Berhasil menghapus data karyawan."
      );
    } catch (DatabaseException $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di sisi server: " . $e->getMessage()
      );
    }
  }

  // Fungsi untuk edit account (hanya warehouse dan frontliner)
  public function edit_account()
  {
    $admin_id = $this->request->getVar("id");

    // Ambil data admin yang akan diedit
    $currentAdmin = $this->db
      ->query("SELECT * FROM admin WHERE admin_id = ?", [$admin_id])
      ->getRowArray();

    // Cek apakah username berubah
    $usernameRule =
      $this->request->getVar("username") !==
      $currentAdmin["admin_username"]
      ? "required|min_length[5]|max_length[50]|is_unique[admin.admin_username]"
      : "required|min_length[5]|max_length[50]";

    // Cek apakah email berubah
    $emailRule =
      $this->request->getVar("email") !== $currentAdmin["admin_email"]
      ? "required|valid_email|is_unique[admin.admin_email]"
      : "required|valid_email";

    // Aturan validasi data yang akan diubah
    $rules = [
      "id" => [
        "rules" => "required",
        "errors" => [
          "required" => "ID harus diisi.",
        ],
      ],
      "username" => [
        "rules" => $usernameRule,
        "errors" => [
          "required" => "Username wajib diisi.",
          "min_length" => "Username minimal harus 5 karakter.",
          "max_length" => "Username maksimal 50 karakter.",
          "is_unique" =>
            "Username sudah digunakan, silakan pilih yang lain.",
        ],
      ],
      "email" => [
        "rules" => $emailRule,
        "errors" => [
          "required" => "Email wajib diisi.",
          "valid_email" => "Email tidak valid.",
          "is_unique" => "Email sudah terdaftar, gunakan email lain.",
        ],
      ],
      "full_name" => [
        "rules" => "required|max_length[100]",
        "errors" => [
          "required" => "Nama lengkap wajib diisi.",
          "max_length" => "Nama lengkap maksimal 100 karakter.",
        ],
      ],
      "nik" => [
        "rules" => "required|min_length[16]|max_length[30]",
        "errors" => [
          "required" => "NIK wajib diisi.",
          "min_length" => "NIK minimal harus 16 karakter.",
          "max_length" => "NIK maksimal 30 karakter.",
        ],
      ],
      "role" => [
        "rules" => "required|in_list[warehouse,frontliner]",
        "errors" => [
          "required" => "Peran wajib dipilih.",
          "in_list" =>
            "Peran harus salah satu dari: warehouse atau frontliner.",
        ],
      ],
      "phone" => [
        "rules" => "required|max_length[20]",
        "errors" => [
          "required" => "Nomor telepon wajib diisi.",
          "max_length" => "Nomor telepon maksimal 20 karakter.",
        ],
      ],
      "gender" => [
        "rules" => "required|in_list[Laki-Laki,Perempuan]",
        "errors" => [
          "required" => "Jenis kelamin wajib dipilih.",
          "in_list" =>
            "Jenis kelamin harus salah satu dari: Laki-Laki atau Perempuan.",
        ],
      ],
      "address" => [
        "rules" => "required|max_length[255]",
        "errors" => [
          "required" => "Alamat wajib diisi.",
          "max_length" => "Alamat maksimal 255 karakter.",
        ],
      ],
    ];

    if (!$this->validate($rules)) {
      return $this->respondWithValidationError(
        "Validasi error",
        $this->validator->getErrors()
      );
    }

    $data = [
      "admin_username" => $this->request->getVar("username"),
      "admin_email" => $this->request->getVar("email"),
      "admin_full_name" => $this->request->getVar("full_name"),
      "admin_phone" => $this->request->getVar("phone"),
      "admin_address" => $this->request->getVar("address"),
      "admin_gender" => $this->request->getVar("gender"),
      "admin_nik" => $this->request->getVar("nik"),
    ];

    try {
      // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
      $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
      $user = $this->db->query($query, [$admin_id])->getRowArray();

      if (!$user) {
        return $this->respondWithError("User tidak di temukan.");
      }

      if ($user["admin_role"] == "superadmin") {
        return $this->respondWithUnauthorized(
          "Hanya warehouse dan frontliner yang dapat diedit"
        );
      }

      // Lakukan update data
      $updateQuery = "UPDATE admin SET 
            admin_username = ?, 
            admin_email = ?, 
            admin_full_name = ?, 
            admin_phone = ?, 
            admin_address = ?, 
            admin_nik = ?, 
            admin_gender = ? 
            WHERE admin_id = ?";

      $this->db->query($updateQuery, [
        $data["admin_username"],
        $data["admin_email"],
        $data["admin_full_name"],
        $data["admin_phone"],
        $data["admin_address"],
        $data["admin_nik"],
        $data["admin_gender"],
        $admin_id,
      ]);

      $result = [
        "data" => [
          "username" => $data["admin_username"],
          "email" => $data["admin_email"],
          "full_name" => $data["admin_full_name"],
          "nik" => $data["admin_nik"],
          "phone" => $data["admin_phone"],
          "gender" => $data["admin_gender"],
          "address" => $data["admin_address"],
        ],
      ];

      return $this->respondWithSuccess(
        "Berhasil mengupdate data karyawan.",
        $result
      );
    } catch (DatabaseException $e) {
      return $this->respondWithError(
        "Terjadi kesalahan di sisi server: " . $e->getMessage()
      );
    }
  }
  // Fungsi untuk mendaftarkan pengguna (hanya warehouse dan frontliner)
  public function register()
  {
    // Validasi input
    $rules = [
      "username" => [
        "rules" =>
          "required|min_length[5]|max_length[50]|is_unique[admin.admin_username]",
        "errors" => [
          "required" => "Username wajib diisi.",
          "min_length" => "Username minimal harus 5 karakter.",
          "max_length" => "Username maksimal 50 karakter.",
          "is_unique" =>
            "Username sudah terdaftar, gunakan username lain.",
        ],
      ],
      "password" => [
        "rules" => "required|min_length[8]",
        "errors" => [
          "required" => "Password wajib diisi.",
          "min_length" => "Password minimal harus 8 karakter.",
        ],
      ],
      "email" => [
        "rules" => "required|valid_email|is_unique[admin.admin_email]",
        "errors" => [
          "required" => "Email wajib diisi.",
          "valid_email" => "Format email tidak valid.",
          "is_unique" => "Email sudah terdaftar, gunakan email lain.",
        ],
      ],
      "full_name" => [
        "rules" => "required|max_length[100]",
        "errors" => [
          "required" => "Nama lengkap wajib diisi.",
          "max_length" => "Nama lengkap maksimal 100 karakter.",
        ],
      ],
      "nik" => [
        "rules" => "required|min_length[16]|max_length[30]",
        "errors" => [
          "required" => "NIK wajib diisi.",
          "min_length" => "NIK minimal harus 16 karakter.",
          "max_length" => "NIK maksimal 30 karakter.",
        ],
      ],
      "role" => [
        "rules" => "required|in_list[warehouse,frontliner]",
        "errors" => [
          "required" => "Peran wajib dipilih.",
          "in_list" =>
            "Peran harus salah satu dari: warehouse atau frontliner.",
        ],
      ],
      "phone" => [
        "rules" => "required|max_length[20]",
        "errors" => [
          "required" => "Nomor telepon wajib diisi.",
          "max_length" => "Nomor telepon maksimal 20 karakter.",
        ],
      ],
      "gender" => [
        "rules" => "required|in_list[Laki-Laki,Perempuan]",
        "errors" => [
          "required" => "Jenis kelamin wajib dipilih.",
          "in_list" =>
            "Jenis kelamin harus salah satu dari: Laki-Laki atau Perempuan.",
        ],
      ],
      "address" => [
        "rules" => "required|max_length[255]",
        "errors" => [
          "required" => "Alamat wajib diisi.",
          "max_length" => "Alamat maksimal 255 karakter.",
        ],
      ],
    ];

    // Validasi input berdasarkan rules
    if (!$this->validate($rules)) {
      return $this->respondWithValidationError(
        "Validasi error",
        $this->validator->getErrors()
      );
    }

    // Ambil data dari request
    $data = [
      "admin_username" => $this->request->getVar("username"),
      "admin_password" => $this->request->getVar("password"),
      "admin_email" => $this->request->getVar("email"),
      "admin_full_name" => $this->request->getVar("full_name"),
      "admin_nik" => $this->request->getVar("nik"),
      "admin_role" => $this->request->getVar("role"),
      "admin_phone" => $this->request->getVar("phone"),
      "admin_gender" => $this->request->getVar("gender"),
      "admin_address" => $this->request->getVar("address"),
    ];

    try {
      // Hash password sebelum menyimpan ke database
      $data["admin_password"] = password_hash(
        $data["admin_password"],
        PASSWORD_DEFAULT
      );

      // Raw query untuk memasukkan data ke tabel admin
      $query = "INSERT INTO admin (admin_username, admin_password, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

      // Eksekusi query
      $this->db->query($query, [
        $data["admin_username"],
        $data["admin_password"],
        $data["admin_email"],
        $data["admin_full_name"],
        $data["admin_nik"],
        $data["admin_role"],
        $data["admin_phone"],
        $data["admin_gender"],
        $data["admin_address"],
      ]);

      return $this->respondWithSuccess("Berhasil Registrasi.");
    } catch (DatabaseException $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di sisi server: " . $e->getMessage()
      );
    }
  }

  public function login()
  {
    $rules = [
      "username" => "required",
      "password" => "required",
    ];

    if (!$this->validate($rules)) {
      return $this->respondWithValidationError(
        "Validasi error",
        $this->validator->getErrors()
      );
    }

    $username = $this->request->getVar("username");
    $password = $this->request->getVar("password");

    try {
      // Query untuk mendapatkan data user berdasarkan username
      $query = "SELECT * FROM admin WHERE admin_username = ?";
      $user = $this->db->query($query, [$username])->getRowArray();

      if ($user && password_verify($password, $user["admin_password"])) {
        // Buat token random
        $token = bin2hex(random_bytes(32)); // Random string sebagai token


        // Simpan token ke database
        $query =
          "INSERT INTO admin_token (admin_token_admin_id, admin_token_token, admin_token_expires_at) VALUES (?, ?, ?)";
        $this->db->query($query, [
          $user["admin_id"],
          $token,
          date("Y-m-d H:i:s", strtotime(datetime: "+1 days")),
        ]);

        // Format respons
        $response = [
          "data" => [
            "id" => (int) $user["admin_id"],
            "username" => $user["admin_username"],
            "email" => $user["admin_email"],
            "full_name" => $user["admin_full_name"],
            "nik" => $user["admin_nik"],
            "role" => $user["admin_role"],
            "phone" => $user["admin_phone"],
            "gender" => $user["admin_gender"],
            "address" => $user["admin_address"],
          ],
          "token" => $token,
        ];

        return $this->respondWithSuccess("Login berhasil.", $response);
      }

      return $this->respondWithUnauthorized(
        "Kredensial yang anda masukan salah."
      );
    } catch (\Exception $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di sisi server: " . $e->getMessage()
      );
    }
  }

  // Fungsi logout (hanya contoh)
  public function logout()
  {
    // Ambil token dari request header (Bearer Token)
    $token = $this->request->getHeaderLine("token");

    // $authHeader = $this->request->getHeader(name: 'Token');

    if (!$token) {
      return $this->respondWithValidationError(
        "Mungkin anda belum memasukan token."
      );
    }

    try {
      // Hapus token dari database
      $query = "DELETE FROM admin_token WHERE token = ?";
      $this->db->query($query, [$token]);

      return $this->respondWithSuccess("Logout berhasil.");
    } catch (\Exception $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di sisi server:: " . $e->getMessage()
      );
    }
  }

  public function sendResetPassword()
  {
    $email = $this->request->getVar("email");

    function generateOTP($length = 6)
    {
      // The minimum number for a 6-digit OTP
      $min = pow(10, $length - 1);
      // The maximum number for a 6-digit OTP
      $max = pow(10, $length) - 1;

      // Generate a random integer between the min and max values
      return random_int($min, $max);
    }

    // Cek apakah email terdaftar
    $query = "SELECT * FROM admin WHERE admin_email = ?";
    $user = $this->db->query($query, binds: [$email])->getRowArray();

    if (!$user) {
      return $this->respondWithError("Email tidak ditemukan.");
    }

    // Buat token reset password
    $otp = generateOTP(); // Random string sebagai token
    $expiresAt = date("Y-m-d H:i:s", strtotime(datetime: "+1 hour"));

    // Simpan token ke tabel admin_token
    try {
      $query =
        "INSERT INTO admin_otp (admin_otp_email, admin_otp_otp, admin_otp_expires_at) VALUES (?, ?, ?)";
      $this->db->query($query, [$user["admin_email"], $otp, $expiresAt]);
    } catch (\Exception $e) {
      return $this->respondWithError(
        "Gagal menyimpan token: " . $e->getMessage()
      );
    }

    // Kirim email menggunakan Mailtrap
    $apiKey = "1154626499e761f6202f9a68cd26e42a"; // Ganti dengan API Key Mailtrap kamu
    $mailtrap = MailtrapClient::initSendingEmails(apiKey: $apiKey);

    $resetLink = base_url("password/reset?token=" . $otp); // Link reset password

    // Buat email
    $emailMessage = (new MailtrapEmail())
      ->from(
        new Address(
          "hello@demomailtrap.com",
          "Admin Perpustakaan Jogja"
        )
      )
      ->to(new Address($email))
      ->subject("Reset Password Anda")
      ->html("
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 20px;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: white;
                    border-radius: 10px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .email-header {
                    background-color: #ba3d4f;
                    color: white;
                    text-align: center;
                    padding: 20px;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                    letter-spacing: 2px;
                }
                .email-body {
                    padding: 30px;
                    line-height: 1.6;
                    color: #333;
                }
                .otp-code {
                    background-color: #f0f0f0;
                    color: #ba3d4f;
                    text-align: center;
                    font-size: 32px;
                    font-weight: bold;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 5px;
                    letter-spacing: 5px;
                }
                .footer {
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                    padding: 10px;
                    background-color: #f4f4f4;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>Reset Password</h1>
                </div>
                <div class='email-body'>
                    <p>Halo,</p>
                    <p>Kami menerima permintaan untuk mereset password akun Anda. Silakan gunakan kode OTP di bawah untuk melanjutkan proses reset password.</p>
                    
                    <div class='otp-code'>
                        $otp
                    </div>

                    <p>Perhatian: Kode OTP ini hanya berlaku selama 1 jam.</p>
                    <p>Salam,<br>Tim Admin Perpustakaan Jogja</p>
                </div>
                <div class='footer'>
                    © 2024 Perpustakaan Jogja. Hak Cipta Dilindungi.
                </div>
            </div>
        </body>
        </html>
    ")
      ->category("Password Reset");

    try {
      // Kirim email
      $response = $mailtrap->send($emailMessage);
      // $messageIds = ResponseHelper::toArray($response);

      return $this->respondWithSuccess(
        "OTP berhasil di kirim ke email anda"
      );
    } catch (\Exception $e) {
      return $this->respondWithError(
        "Terdapat kesalahan di sisi server:: " . $e->getMessage()
      );
    }
  }

  public function resetPassword()
  {
    $otp = $this->request->getVar("otp"); // OTP entered by the user
    $email = $this->request->getVar("email"); // Email associated with the OTP
    $newPassword = $this->request->getVar("new_password");

    // Validasi input
    $rules = [
      "otp" => "required",
      "email" => "required|valid_email", // Validate email
      "new_password" => "required|min_length[8]",
    ];

    if (!$this->validate($rules)) {
      return $this->respondWithValidationError(
        "Validasi error",
        $this->validator->getErrors()
      );
    }

    // Cek apakah email ada di database
    $emailQuery = "SELECT * FROM admin WHERE admin_email = ?";
    $adminData = $this->db->query($emailQuery, [$email])->getRowArray();

    if (!$adminData) {
      return $this->respondWithUnauthorized("Email tidak ditemukan.");
    }

    // Cari token di database dan cek masa berlaku
    $query =
      "SELECT * FROM admin_otp WHERE admin_otp_email = ? AND admin_otp_otp = ? AND admin_otp_expires_at > NOW()";
    $tokenData = $this->db->query($query, [$email, $otp])->getRowArray();

    if (!$tokenData) {
      return $this->respondWithUnauthorized(
        "OTP salah atau sudah kedaluwarsa."
      );
    }

    // Hash password baru
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password di tabel admin
    try {
      $query = "UPDATE admin SET admin_password = ? WHERE admin_id = ?";
      $this->db->query($query, [$hashedPassword, $adminData["admin_id"]]); // Pastikan menggunakan admin_id, bukan email
    } catch (\Exception $e) {
      return $this->respondWithError(
        "Gagal memperbarui password: " . $e->getMessage()
      );
    }

    // Hapus token setelah digunakan
    try {
      $query =
        "DELETE FROM admin_otp WHERE admin_otp_email = ? AND admin_otp_otp = ?";
      $this->db->query($query, [$email, $otp]);
    } catch (\Exception $e) {
      return $this->respondWithError(
        "Gagal menghapus token: " . $e->getMessage()
      );
    }

    return $this->respondWithSuccess("Password berhasil direset.");
  }
}
