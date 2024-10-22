<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;


use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;
use Config\Services;

$pager = service('pager');

class AuthController extends AuthorizationController
{
  protected $format = 'json';

  public function get_all_users()
  {
    $db = \Config\Database::connect();

    $tokenValidation = $this->validateToken('superadmin');

    if ($tokenValidation !== true) {
      return $this->respond($tokenValidation, $tokenValidation['status']);
    }

    $limit = $this->request->getVar('limit') ?? 10;
    $page = $this->request->getVar('page') ?? 1;
    $search = $this->request->getVar(index: 'search');
    $sort = $this->request->getVar(index: 'sort') ?? '';
    $filters = $this->request->getVar('filter') ?? [];
    $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

    $offset = ($page - 1) * $limit;

    $query = "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address FROM admin";
    $conditions = [];
    $params = [];

    if ($search) {
      $conditions[] = "(admin_username LIKE ? OR admin_full_name LIKE ? OR admin_email LIKE ? OR admin_nik LIKE ? OR admin_phone LIKE ? OR admin_address LIKE ? OR admin_gender LIKE ?)";
      $searchTerm = "%" . $search . "%";
      $params = array_fill(0, 7, $searchTerm);
    }

    $filterMapping = [
      'username' => 'admin_username',
      'full_name' => 'admin_full_name',
      'email' => 'admin_email',
      'role' => 'admin_role',
      'nik' => 'admin_nik',
      'phone' => 'admin_phone',
      'gender' => 'admin_gender',
      'address' => 'admin_address'
    ];

    if (!empty($sort)) {
      $sortField = ltrim($sort, '-');
      $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
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
      $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    if ($enablePagination) {
      $query .= " LIMIT ? OFFSET ?";
      $params[] = (int) $limit;
      $params[] = (int) $offset;
    }

    try {
      $users = $db->query($query, $params)->getResultArray();

      $result = [];
      foreach ($users as $user) {
        $result[] = [
          'id' => (int) $user['admin_id'],
          'username' => $user['admin_username'],
          'email' => $user['admin_email'],
          'full_name' => $user['admin_full_name'],
          'nik' => $user['admin_nik'],
          'role' => $user['admin_role'],
          'phone' => $user['admin_phone'],
          'gender' => $user['admin_gender'],
          'address' => $user['admin_address'],
        ];
      }





      $pagination = new \stdClass();
      if ($enablePagination) {
        $totalQuery = "SELECT COUNT(*) as total FROM admin";
        if (!empty($conditions)) {
          $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $pagination = [
          'total_data' => (int) $total,
          'jumlah_page' => (int) $jumlah_page,
          'prev' => ($page > 1) ? $page - 1 : null,
          'page' => (int) $page,
          'next' => ($page < $jumlah_page) ? $page + 1 : null,
          'start' => ($page - 1) * $limit + 1,
          'end' => min($page * $limit, $total),
          'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
        ];
      }

      return $this->respondWithSuccess('Berhasil mendapatkan data karyawan', [
        'data' => $result,
        'pagination' => $pagination
      ]);

    } catch (DatabaseException $e) {
      return $this->respondWithError('Terdapat kesalahan di server: ' . $e->getMessage());
    }
  }

  public function get_user_by_id()
  {
    $db = \Config\Database::connect();

    $admin_id = $this->request->getVar(index: 'id');

    $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

    if ($tokenValidation !== true) {
      return $this->respond($tokenValidation, $tokenValidation['status']);
    }

    try {
      $query = "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address  FROM admin WHERE admin_id = ?";
      $user = $db->query($query, [$admin_id])->getRowArray();


      $data = [

      ];

      if (!$user) {
        return $this->respondWithSuccess('Data tidak tersedia.', $data);
      }




      $data = [
        'data' => [
          'id' => (int) $user['admin_id'],
          'username' => $user['admin_username'],
          'email' => $user['admin_email'],
          'full_name' => $user['admin_full_name'],
          'nik' => $user['admin_nik'],
          'role' => $user['admin_role'],
          'phone' => $user['admin_phone'],
          'gender' => $user['admin_gender'],
          'address' => $user['admin_address'],
        ]
      ];


      return $this->respondWithSuccess('Data karyawan di temukan.', $data);
    } catch (DatabaseException $e) {
      return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
    }
  }

  public function delete_account()
  {
    $db = \Config\Database::connect();
    $admin_id = $this->request->getVar('id');


    try {
      // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
      $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
      $user = $db->query($query, [$admin_id])->getRowArray();

      if (!$user) {
        return $this->respondWithError('User tidak di temukan');
      }

      if ($user['admin_role'] !== 'warehouse' && $user['admin_role'] !== 'frontliner') {
        return $this->respondWithUnauthorized('Hanya wearhouse dan frontliner yang dapat membuat akun.');
      }

      // Lakukan penghapusan data
      $deleteQuery = "DELETE FROM admin WHERE admin_id = ?";
      $db->query($deleteQuery, [$admin_id]);

      return $this->respondWithSuccess('Berhasil menghapus data karyawan.');
    } catch (DatabaseException $e) {
      return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
    }
  }

  // Fungsi untuk edit account (hanya warehouse dan frontliner)
  public function edit_account($admin_id)
  {
    $db = \Config\Database::connect();

    // Aturan validasi data yang akan diubah
    $rules = [
      'id' => [
        'rules' => 'required',
        'errors' => [
          'required' => 'ID harus diisi.'
        ]
      ],
      'username' => [
        'rules' => 'required|min_length[5]|max_length[50]|is_unique[admin.admin_username]',
        'errors' => [
          'required' => 'Username wajib diisi.',
          'min_length' => 'Username minimal harus 5 karakter.',
          'max_length' => 'Username maksimal 50 karakter.',
          'is_unique' => 'Username sudah digunakan, silakan pilih yang lain.'
        ]
      ],
      'password' => [
        'rules' => 'required|min_length[8]',
        'errors' => [
          'required' => 'Password wajib diisi.',
          'min_length' => 'Password minimal harus 8 karakter.'
        ]
      ],
      'email' => [
        'rules' => 'required|valid_email|is_unique[admin.admin_email]',
        'errors' => [
          'required' => 'Email wajib diisi.',
          'valid_email' => 'Email tidak valid.',
          'is_unique' => 'Email sudah terdaftar, gunakan email lain.'
        ]
      ],
      'full_name' => [
        'rules' => 'required|max_length[100]',
        'errors' => [
          'required' => 'Nama lengkap wajib diisi.',
          'max_length' => 'Nama lengkap maksimal 100 karakter.'
        ]
      ],
      'nik' => [
        'rules' => 'required|min_length[16]|max_length[30]',
        'errors' => [
          'required' => 'NIK wajib diisi.',
          'min_length' => 'NIK minimal harus 16 karakter.',
          'max_length' => 'NIK maksimal 30 karakter.'
        ]
      ],
      'role' => [
        'rules' => 'required|in_list[warehouse,frontliner]',
        'errors' => [
          'required' => 'Peran wajib dipilih.',
          'in_list' => 'Peran harus salah satu dari: warehouse atau frontliner.'
        ]
      ],
      'phone' => [
        'rules' => 'required|max_length[20]',
        'errors' => [
          'required' => 'Nomor telepon wajib diisi.',
          'max_length' => 'Nomor telepon maksimal 20 karakter.'
        ]
      ],
      'gender' => [
        'rules' => 'required|in_list[male,female]',
        'errors' => [
          'required' => 'Jenis kelamin wajib dipilih.',
          'in_list' => 'Jenis kelamin harus salah satu dari: male atau female.'
        ]
      ],
      'address' => [
        'rules' => 'required|max_length[255]',
        'errors' => [
          'required' => 'Alamat wajib diisi.',
          'max_length' => 'Alamat maksimal 255 karakter.'
        ]
      ]
    ];

    if (!$this->validate($rules)) {
      return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
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
        return $this->respondWithError('User tidak di temukan.');
      }

      if ($user['admin_role'] !== 'warehouse' && $user['admin_role'] !== 'frontliner') {
        return $this->respondWithUnauthorized('Hanya wearhouse dan frontliner yang dapat diedit');
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


      $result = [
        'data' => [
          'username' => $data['admin_username'],
          'email' => $data['admin_email'],
          'full_name' => $data['admin_full_name'],
          'phone' => $data['admin_phone'],
          'address' => $data['admin_address']
        ]
      ];

      return $this->respondWithSuccess('Berhasil mengupdate data karywan.', $result);
    } catch (DatabaseException $e) {
      return $this->respondWithError('Terjadi kesalahan di sisi server: ' . $e->getMessage());
    }
  }

  // Fungsi untuk mendaftarkan pengguna (hanya warehouse dan frontliner)
  public function register()
  {
    $db = \Config\Database::connect();

    // Validasi input
    $rules = [
      'username' => [
        'rules' => 'required|min_length[5]|max_length[50]|is_unique[admin.admin_username]',
        'errors' => [
          'required' => 'Username wajib diisi.',
          'min_length' => 'Username minimal harus 5 karakter.',
          'max_length' => 'Username maksimal 50 karakter.',
          'is_unique' => 'Username sudah terdaftar, gunakan username lain.'
        ]
      ],
      'password' => [
        'rules' => 'required|min_length[8]',
        'errors' => [
          'required' => 'Password wajib diisi.',
          'min_length' => 'Password minimal harus 8 karakter.'
        ]
      ],
      'email' => [
        'rules' => 'required|valid_email|is_unique[admin.admin_email]',
        'errors' => [
          'required' => 'Email wajib diisi.',
          'valid_email' => 'Format email tidak valid.',
          'is_unique' => 'Email sudah terdaftar, gunakan email lain.'
        ]
      ],
      'full_name' => [
        'rules' => 'required|max_length[100]',
        'errors' => [
          'required' => 'Nama lengkap wajib diisi.',
          'max_length' => 'Nama lengkap maksimal 100 karakter.'
        ]
      ],
      'nik' => [
        'rules' => 'required|min_length[16]|max_length[30]',
        'errors' => [
          'required' => 'NIK wajib diisi.',
          'min_length' => 'NIK minimal harus 16 karakter.',
          'max_length' => 'NIK maksimal 30 karakter.'
        ]
      ],
      'role' => [
        'rules' => 'required|in_list[warehouse,frontliner]',
        'errors' => [
          'required' => 'Peran wajib dipilih.',
          'in_list' => 'Peran harus salah satu dari: warehouse atau frontliner.'
        ]
      ],
      'phone' => [
        'rules' => 'required|max_length[20]',
        'errors' => [
          'required' => 'Nomor telepon wajib diisi.',
          'max_length' => 'Nomor telepon maksimal 20 karakter.'
        ]
      ],
      'gender' => [
        'rules' => 'required|in_list[Laki-Laki,Perempuan]',
        'errors' => [
          'required' => 'Jenis kelamin wajib dipilih.',
          'in_list' => 'Jenis kelamin harus salah satu dari: Laki-Laki atau Perempuan.'
        ]
      ],
      'address' => [
        'rules' => 'required|max_length[255]',
        'errors' => [
          'required' => 'Alamat wajib diisi.',
          'max_length' => 'Alamat maksimal 255 karakter.'
        ]
      ]
    ];

    // Validasi input berdasarkan rules
    if (!$this->validate($rules)) {
      return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
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

      return $this->respondWithSuccess('Berhasil Registrasi.', );
    } catch (DatabaseException $e) {
      return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
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
      return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
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
          date('Y-m-d H:i:s', strtotime(datetime: '+1 hour'))
        ]);

        // Format respons
        $response = [
          'data' => [
            'id' => (int) $user['admin_id'],
            'username' => $user['admin_username'],
            'email' => $user['admin_email'],
            'full name' => $user['admin_full_name'],
            'nik' => $user['admin_nik'],
            'role' => $user['admin_role'],
            'phone' => $user['admin_phone'],
            'gender' => $user['admin_gender'],
            'address' => $user['admin_address']
          ],
          'token' => $token
        ];

        return $this->respondWithSuccess('Login berhasil.', $response);
      }

      return $this->respondWithUnauthorized('Kredensial yang anda masukan salah.');
    } catch (\Exception $e) {
      return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
    }
  }

  // Fungsi logout (hanya contoh)
  public function logout()
  {
    $db = \Config\Database::connect();

    // Ambil token dari request header (Bearer Token)
    $token = $this->request->getHeaderLine('token');


    // $authHeader = $this->request->getHeader(name: 'Token');



    if (!$token) {
      return $this->respondWithValidationError('Mungkin anda belum memasukan token.');
    }

    try {
      // Hapus token dari database
      $query = "DELETE FROM admin_token WHERE token = ?";
      $db->query($query, [$token]);

      return $this->respondWithSuccess('Logout berhasil.');
    } catch (\Exception $e) {
      return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
    }
  }

  public function sendResetPassword()
  {
    $email = $this->request->getVar('email');
    $db = \Config\Database::connect();


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
    $user = $db->query($query, binds: [$email])->getRowArray();

    if (!$user) {
      return $this->respondWithError('Email tidak ditemukan.');
    }

    // Buat token reset password
    $otp = generateOTP();// Random string sebagai token
    $expiresAt = date('Y-m-d H:i:s', strtotime(datetime: '+1 hour'));



    // Simpan token ke tabel admin_token
    try {
      $query = "INSERT INTO admin_otp (admin_id, admin_otp_otp, expires_at) VALUES (?, ?, ?)";
      $db->query($query, [$user['admin_id'], $otp, $expiresAt]);
    } catch (\Exception $e) {
      return $this->respondWithError('Gagal menyimpan token: ' . $e->getMessage());
    }

    // Kirim email menggunakan Mailtrap
    $apiKey = '1154626499e761f6202f9a68cd26e42a'; // Ganti dengan API Key Mailtrap kamu
    $mailtrap = MailtrapClient::initSendingEmails(
      apiKey: $apiKey,
    );

    $resetLink = base_url('password/reset?token=' . $otp); // Link reset password

    // Buat email
    $emailMessage = (new MailtrapEmail())
      ->from(new Address('hello@demomailtrap.com', 'Admin Perpustakaan Jogja')) // Ganti dengan alamat pengirim
      ->to(new Address($email)) // Kirim ke alamat email pengguna
      ->subject('Reset Password Anda')
      // ->text("Halo,\n\nKami menerima permintaan untuk mereset password akun Anda. Silakan klik tautan berikut untuk mereset password Anda: $resetLink\n\nTautan ini hanya berlaku selama 1 jam.\n\nSalam,\nAdmin Perpustakaan Jogja")
      ->html("<!DOCTYPE html>
<html lang=\"en\">
  <head>
    <meta charset=\"UTF-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
    <meta http-equiv=\"X-UA-Compatible\" content=\"ie=edge\" />
    <title>Static Template</title>

    <link
      href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap\"
      rel=\"stylesheet\"
    />
  </head>
  <body
    style=\"
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: #ffffff;
      font-size: 14px;
    \"
  >
    <div
      style=\"
        max-width: 680px;
        margin: 0 auto;
        padding: 45px 30px 60px;
        background: #f4f7ff;
        background-image: url(https://pbs.twimg.com/profile_images/1061465135091408897/oEg-3OE-_400x400.jpg);
        background-repeat: no-repeat;
        background-size: 800px 452px;
        background-position: top center;
        font-size: 14px;
        color: #434343;
      \"
    >
      <header>
        <table style=\"width: 100%;\">
          <tbody>
            <tr style=\"height: 0;\">
              <td>
                <img
                  alt=\"\"
                  src=\"https://archisketch-resources.s3.ap-northeast-2.amazonaws.com/vrstyler/1663574980688_114990/archisketch-logo\"
                  height=\"30px\"
                />
              </td>
              <td style=\"text-align: right;\">
                <span
                  style=\"font-size: 16px; line-height: 30px; color: #ffffff;\"
                  >12 Nov, 2021</span
                >
              </td>
            </tr>
          </tbody>
        </table>
      </header>

      <main>
        <div
          style=\"
            margin: 0;
            margin-top: 70px;
            padding: 92px 30px 115px;
            background: #ffffff;
            border-radius: 30px;
            text-align: center;
          \"
        >
          <div style=\"width: 100%; max-width: 489px; margin: 0 auto;\">
            <h1
              style=\"
                margin: 0;
                font-size: 24px;
                font-weight: 500;
                color: #1f1f1f;
              \"
            >
              Your OTP
            </h1>
            <p
              style=\"
                margin: 0;
                margin-top: 17px;
                font-size: 16px;
                font-weight: 500;
              \"
            >
              Hey Tomy,
            </p>
            <p
              style=\"
                margin: 0;
                margin-top: 17px;
                font-weight: 500;
                letter-spacing: 0.56px;
              \"
            >
              Thank you for choosing Archisketch Company. Use the following OTP
              to complete the procedure to change your email address. OTP is
              valid for
              <span style=\"font-weight: 600; color: #1f1f1f;\">5 minutes</span>.
              Do not share this code with others, including Archisketch
              employees.
            </p>
            <p
              style=\"
                margin: 0;
                margin-top: 60px;
                font-size: 40px;
                font-weight: 600;
                letter-spacing: 25px;
                color: #ba3d4f;
              \"
            >
              $otp 
            </p>
          </div>
        </div>

        <p
          style=\"
            max-width: 400px;
            margin: 0 auto;
            margin-top: 90px;
            text-align: center;
            font-weight: 500;
            color: #8c8c8c;
          \"
        >
          Need help? Ask at
          <a
            href=\"mailto:archisketch@gmail.com\"
            style=\"color: #499fb6; text-decoration: none;\"
            >archisketch@gmail.com</a
          >
          or visit our
          <a
            href=\"\"
            target=\"_blank\"
            style=\"color: #499fb6; text-decoration: none;\"
            >Help Center</a
          >
        </p>
      </main>

      <footer
        style=\"
          width: 100%;
          max-width: 490px;
          margin: 20px auto 0;
          text-align: center;
          border-top: 1px solid #e6ebf1;
        \"
      >
        <p
          style=\"
            margin: 0;
            margin-top: 40px;
            font-size: 16px;
            font-weight: 600;
            color: #434343;
          \"
        >
          Archisketch Company
        </p>
        <p style=\"margin: 0; margin-top: 8px; color: #434343;\">
          Address 540, City, State.
        </p>
        <div style=\"margin: 0; margin-top: 16px;\">
          <a href=\"\" target=\"_blank\" style=\"display: inline-block;\">
            <img
              width=\"36px\"
              alt=\"Facebook\"
              src=\"https://archisketch-resources.s3.ap-northeast-2.amazonaws.com/vrstyler/1661502815169_682499/email-template-icon-facebook\"
            />
          </a>
          <a
            href=\"\"
            target=\"_blank\"
            style=\"display: inline-block; margin-left: 8px;\"
          >
            <img
              width=\"36px\"
              alt=\"Instagram\"
              src=\"https://archisketch-resources.s3.ap-northeast-2.amazonaws.com/vrstyler/1661504218208_684135/email-template-icon-instagram\"
          /></a>
          <a
            href=\"\"
            target=\"_blank\"
            style=\"display: inline-block; margin-left: 8px;\"
          >
            <img
              width=\"36px\"
              alt=\"Twitter\"
              src=\"https://archisketch-resources.s3.ap-northeast-2.amazonaws.com/vrstyler/1661503043040_372004/email-template-icon-twitter\"
            />
          </a>
          <a
            href=\"\"
            target=\"_blank\"
            style=\"display: inline-block; margin-left: 8px;\"
          >
            <img
              width=\"36px\"
              alt=\"Youtube\"
              src=\"https://archisketch-resources.s3.ap-northeast-2.amazonaws.com/vrstyler/1661503195931_210869/email-template-icon-youtube\"
          /></a>
        </div>
        <p style=\"margin: 0; margin-top: 16px; color: #434343;\">
          Copyright © 2022 Company. All rights reserved.
        </p>
      </footer>
    </div>
  </body>
</html>")

      ->category('Password Reset');

    try {
      // Kirim email
      $response = $mailtrap->send($emailMessage);
      // $messageIds = ResponseHelper::toArray($response);



      return $this->respondWithSuccess('OTP berhasil di kirim ke email anda', );
    } catch (\Exception $e) {
      return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
    }
  }

  public function resetPassword()
  {
    $db = \Config\Database::connect();
    $otp = $this->request->getVar('otp');
    $newPassword = $this->request->getVar('new_password');

    // Validasi input
    $rules = [
      'otp' => 'required',
      'new_password' => 'required|min_length[8]'
    ];

    if (!$this->validate($rules)) {
      return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
    }

    // Cari token di database dan cek masa berlaku
    $query = "SELECT * FROM admin_otp WHERE admin_otp_otp = ? AND expires_at > NOW()";
    $tokenData = $db->query($query, [$otp])->getRowArray();

    if (!$tokenData) {
      return $this->respondWithUnauthorized('OTP salah atau sudah kedaurasa.');
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
      $query = "DELETE FROM admin_otp WHERE admin_otp_otp = ?";
      $db->query($query, [$otp]);
    } catch (\Exception $e) {
      return $this->respondWithError('Gagal menghapus token: ' . $e->getMessage());
    }

    return $this->respondWithSuccess('Password berhasil di reset.');
  }

}



