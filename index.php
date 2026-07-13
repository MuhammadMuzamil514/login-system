<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

date_default_timezone_set('Asia/Karachi'); 

$dbFile = 'database.json';

$host    = 'localhost';
$db      = 'postmantask_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(["status" => "Error", "message" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

if (!file_exists($dbFile)) {
    file_put_contents($dbFile, json_encode([
        ["id" => 1, "name" => "Laptop", "price" => 800],
        ["id" => 2, "name" => "Phone", "price" => 400]
    ], JSON_PRETTY_PRINT));
}

function syncJsonFile($pdo, $dbFile) {
    try {
        $stmt = $pdo->query("SELECT * FROM items");
        $items = $stmt->fetchAll();
        file_put_contents($dbFile, json_encode($items, JSON_PRETTY_PRINT));
    } catch (\PDOException $e) {
       
    }
}

  $method = $_SERVER['REQUEST_METHOD'];
  $input = json_decode(file_get_contents("php://input"), true);

$type = $input['type'] ?? 'item';

if ($type === 'user') {

    
    if ($method === 'POST') {
        $action = $input['action'] ?? '';

        // 1. SIGNUP ACTION
        if ($action === 'signup') {
            if (!empty($input['email']) && !empty($input['password'])) {
                try {
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                    $check->execute([':email' => $input['email']]);
                    if ($check->fetch()) {
                        http_response_code(400);
                        echo json_encode(["status" => "Error", "message" => "Email already registered"]);
                        exit;
                    }

                    $incomingRole = isset($input['role']) ? strtolower(trim($input['role'])) : 'author';
                    $role = in_array($incomingRole, ['admin', 'author']) ? $incomingRole : 'author';
                    $hashedPassword = password_hash($input['password'], PASSWORD_BCRYPT);

                    $sql = "INSERT INTO users (email, password, role) VALUES (:email, :password, :role)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':email' => $input['email'],
                        ':password' => $hashedPassword,
                        ':role' => $role
                    ]);

                    echo json_encode(["status" => "Success", "message" => "User registered successfully", "role" => $role]);
                } catch (\PDOException $e) {
                    http_response_code(500);
                    echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["status" => "Error", "message" => "Missing required email or password"]);
            }
        }
        
        // 2. LOGIN ACTION
        elseif ($action === 'login') {
            if (!empty($input['email']) && !empty($input['password'])) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
                    $stmt->execute([':email' => $input['email']]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($input['password'], $user['password'])) {
                        $mockToken = base64_encode($user['email'] . ":" . $user['role']);
                        echo json_encode([
                            "status" => "Success",
                            "message" => "Login successful",
                            "token" => $mockToken,
                            "role" => $user['role']
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(["status" => "Error", "message" => "Invalid credentials"]);
                    }
                } catch (\PDOException $e) {
                    http_response_code(500);
                    echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["status" => "Error", "message" => "Missing email or password"]);
            }
        }

        // 3. FORGOT PASSWORD ACTION
        elseif ($action === 'forgot') {
            if (!empty($input['email'])) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                    $stmt->execute([':email' => $input['email']]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $token = bin2hex(random_bytes(16));
                        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                        $update = $pdo->prepare("UPDATE users SET reset_token = :token, token_expiry = :expiry WHERE id = :id");
                        $update->execute([':token' => $token, ':expiry' => $expiry, ':id' => $user['id']]);

                        echo json_encode([
                            "status" => "Success",
                            "message" => "Reset token generated successfully",
                            "reset_token" => $token
                        ]);
                    } else {
                        http_response_code(404);
                        echo json_encode(["status" => "Error", "message" => "Email not found"]);
                    }
                } catch (\PDOException $e) {
                    http_response_code(500);
                    echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["status" => "Error", "message" => "Email is required"]);
            }
        }
    } 
    
    // 4. RESET PASSWORD ACTION (PUT Method)
    elseif ($method === 'PUT' && ($input['action'] ?? '') === 'reset') {
        if (!empty($input['reset_token']) && !empty($input['new_password'])) {
            try {
                $stmt = $pdo->prepare("SELECT id, token_expiry FROM users WHERE reset_token = :token");
                $stmt->execute([':token' => $input['reset_token']]);
                $user = $stmt->fetch();

          if ($user) {
                  $currentTime = date('Y-m-d H:i:s');
                    
             if ($user['token_expiry'] > $currentTime) {
                $newHash = password_hash($input['new_password'], PASSWORD_BCRYPT);
                 $update = $pdo->prepare("UPDATE users SET password = :pass, reset_token = NULL, token_expiry = NULL WHERE id = :id");
                 $update->execute([':pass' => $newHash, ':id' => $user['id']]);

                 echo json_encode(["status" => "Success", "message" => "Password updated successfully"]);
          } else {
                      http_response_code(400);
                       echo json_encode(["status" => "Error", "message" => "Token has expired. Please request a new one."]);
                    }
         } else {
                    http_response_code(400);
                    echo json_encode(["status" => "Error", "message" => "Token invalid or not found"]);
                }
            } catch (\PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["status" => "Error", "message" => "Missing reset_token or new_password parameters"]);
        }
    }

} else {
     
       switch ($method) {
        case 'GET': 
            try {
                $stmt = $pdo->query("SELECT * FROM items");
                $items = $stmt->fetchAll();
               
                file_put_contents($dbFile, json_encode($items, JSON_PRETTY_PRINT));
                echo json_encode($items);
            } catch (\PDOException $e) {
                echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
            }
            break;

        case 'POST': // CREATE
            if (!empty($input['name']) && !empty($input['price'])) {
                try {
                    $sql = "INSERT INTO items (name, price) VALUES (:name, :price)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name'  => $input['name'],
                        ':price' => (float)$input['price']
                    ]);
                    
                    $newId = $pdo->lastInsertId();
                    syncJsonFile($pdo, $dbFile);
                    
            echo json_encode([
                  "status" => "Success", 
                  "message" => "Item added ", 
              "item" => [
                "id" => (int)$newId,

               "name" => $input['name'],"price" => (float)$input['price']]]);
              } 
              catch  (\PDOException $e) {
            echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
                   }}
        else 
                 {
             echo json_encode(["status" => "Error", "message" => "Invalid Postman data"]);
             }
                break;
         case 'PUT': 
            // UPDATE
         if 
            (!empty($input['id']) && !empty($input['name']) && !empty($input['price'])) {
         try 
              {
                $sql = "UPDATE items SET name = :name, price = :price WHERE id = :id";
              $stmt = $pdo->prepare($sql);

              $stmt->execute([':id'    => $input['id'],':name'  => $input['name'],':price' => (float)$input['price']]);
          if ($stmt->rowCount() > 0) {syncJsonFile($pdo, $dbFile);
              echo json_encode(["status" => "Success", "message" => "Item updated in MySQL & database.json"]);}
          else
             {
                echo json_encode(["status" => "Error", "message" => "ID not found or no changes made"]);}} 
          catch (\PDOException $e) {
              echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
              }}
           else {
               echo json_encode(["status" => "Error", "message" => "Missing required fields for update"]);}
          break;
                                         
     case 'DELETE': 
        // DELETE (Cleaned and Token Protected)
          $headers = getallheaders();$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
          if 
          (strpos($authHeader, 'Bearer ') === 0)
              {$token = substr($authHeader, 7);
              $decoded = explode(':', base64_decode($token));
              $userRole = $decoded[1] ?? '';
        if
            ($userRole !== 'admin') {http_response_code(403);
            echo json_encode(["status" => "Error", "message" => "Access denied. Admins only."]);
      exit;
           }} 
    else 
             {
         http_response_code(401);
      echo json_encode(["status" => "Error", "message" => "Missing or broken Authentication token context"]);
          exit;
             }
      if 
            (!empty($input['id']))
                 {
        try 
            {$sql = "DELETE FROM items WHERE id = :id";
          $stmt = $pdo->prepare($sql);
         $stmt->execute([':id' => $input['id']]);
        if 
         ($stmt->rowCount() > 0) {syncJsonFile($pdo, $dbFile);
          echo json_encode(["status" => "Success", "message" => "Item deleted from MySQL & database.json"]);
                  } 
     else 
                {
          echo json_encode(["status" => "Error", "message" => "ID not found"]);}}
     catch (\PDOException $e) {
         echo json_encode(["status" => "Error", "message" => $e->getMessage()]);
             }} 
       else {
            echo json_encode(["status" => "Error", "message" => "Missing ID for deletion"]);}
     break;
                }
                }


?>