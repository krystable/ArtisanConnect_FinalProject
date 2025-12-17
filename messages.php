<?php
require_once 'database.php';
startSession();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$conn = getDBConnection();

$active_recipient = isset($_GET['recipient_id']) ? (int)$_GET['recipient_id'] : 0;
$error = '';

//Sending Message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $recipient_id = (int)$_POST['recipient_id'];
        $message = sanitize($_POST['message']);
        
        if (!empty($message) && $recipient_id > 0) {
            $sender_type = $user_type; 

            $stmt = $conn->prepare("INSERT INTO messages (senderId, receiverId, senderType, message, is_read) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param("iiss", $user_id, $recipient_id, $sender_type, $message);
            $stmt->execute();
            
        
            header("Location: messages.php?recipient_id=$recipient_id");
            exit();
        }
    }
}


$conversations = [];
$contact_ids = [];

if ($user_type === 'client') {
  
    $sql_sent = "SELECT DISTINCT receiverId as id FROM messages WHERE senderId = ? AND senderType = 'client'";
   
    $sql_received = "SELECT DISTINCT senderId as id FROM messages WHERE receiverId = ? AND senderType = 'artisan'";
} else {

    $sql_sent = "SELECT DISTINCT receiverId as id FROM messages WHERE senderId = ? AND senderType = 'artisan'";

    $sql_received = "SELECT DISTINCT senderId as id FROM messages WHERE receiverId = ? AND senderType = 'client'";
}


$stmt = $conn->prepare($sql_sent);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $contact_ids[] = $row['id'];
}
$stmt->close();

$stmt = $conn->prepare($sql_received);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $contact_ids[] = $row['id'];
}
$stmt->close();

$contact_ids = array_unique($contact_ids);

if (!empty($contact_ids)) {
    $ids_str = implode(',', $contact_ids); 
    $table = ($user_type === 'client') ? 'artisans' : 'clients';
    $id_col = ($user_type === 'client') ? 'artisanId' : 'clientId';
    
    $sql = "SELECT $id_col as id, name, profile_image FROM $table WHERE $id_col IN ($ids_str)";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
    }
}

if ($active_recipient > 0) {
    $exists = false;
    foreach ($conversations as $conv) {
        if ($conv['id'] == $active_recipient) {
            $exists = true;
            $active_user = $conv;
            break;
        }
    }
    
    if (!$exists) {
        $table = ($user_type === 'client') ? 'artisans' : 'clients';
        $id_field = ($user_type === 'client') ? 'artisanId' : 'clientId';
        
        $stmt = $conn->prepare("SELECT $id_field as id, name, profile_image FROM $table WHERE $id_field = ?");
        $stmt->bind_param("i", $active_recipient);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $active_user = $row;
            array_unshift($conversations, $row); 
        }
    }
}

$messages = [];
if ($active_recipient > 0) {
    if ($user_type === 'client') {
        $sql = "SELECT * FROM messages 
                WHERE (senderId = ? AND senderType = 'client' AND receiverId = ?) 
                   OR (senderId = ? AND senderType = 'artisan' AND receiverId = ?) 
                ORDER BY created_at ASC";
    } else {
        $sql = "SELECT * FROM messages 
                WHERE (senderId = ? AND senderType = 'artisan' AND receiverId = ?) 
                   OR (senderId = ? AND senderType = 'client' AND receiverId = ?) 
                ORDER BY created_at ASC";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $active_recipient, $active_recipient, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    // read
    $mark_read_type = ($user_type === 'client') ? 'artisan' : 'client';
    $update_sql = "UPDATE messages SET is_read = 1 WHERE senderId = ? AND senderType = ? AND receiverId = ? AND is_read = 0";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("isi", $active_recipient, $mark_read_type, $user_id);
    $stmt->execute();
    $stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-container">
        <!-- Sidebar -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                Messages
            </div>
            <div class="conversation-list">
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conv): 
                         $has_img = !empty($conv['profile_image']);
                         $conv_img = $has_img ? htmlspecialchars($conv['profile_image']) : '';
                    ?>
                        <a href="messages.php?recipient_id=<?php echo $conv['id']; ?>" class="conversation-item <?php echo $active_recipient == $conv['id'] ? 'active' : ''; ?>">
                            <div class="user-avatar-small" style="<?php echo $has_img ? "background-image: url('$conv_img')" : "background-color: #eee; display: flex; align-items: center; justify-content: center;"; ?>">
                                <?php if (!$has_img): ?>
                                    <i class="fas fa-user" style="color: #999;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-name-small"><?php echo htmlspecialchars($conv['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 1rem; text-align: center; color: #999;">No conversations yet.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($active_recipient > 0): ?>
                <?php 
                    $has_active_img = !empty($active_user['profile_image']);
                    $active_img = $has_active_img ? htmlspecialchars($active_user['profile_image']) : '';
                ?>
                <div class="chat-header">
                    <div class="user-avatar-small" style="<?php echo $has_active_img ? "background-image: url('$active_img')" : "background-color: #eee; display: flex; align-items: center; justify-content: center;"; ?>">
                         <?php if (!$has_active_img): ?>
                            <i class="fas fa-user" style="color: #999;"></i>
                         <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name-small"><?php echo htmlspecialchars($active_user['name']); ?></div>
                    </div>
                </div>
                
                <div class="messages-display" id="messagesDisplay">
                    <?php foreach ($messages as $msg): ?>
                        <?php 
                            $is_me = ($msg['senderType'] === $user_type && $msg['senderId'] === $user_id);
                        ?>
                        <div class="message-bubble <?php echo $is_me ? 'message-sent' : 'message-received'; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            <div class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-input-area">
                    <form method="POST" action="" class="chat-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="recipient_id" value="<?php echo $active_recipient; ?>">
                        <input type="text" name="message" class="chat-input" placeholder="Type a message..." required autocomplete="off">
                        <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>Select a conversation to start messaging</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const messagesDisplay = document.getElementById('messagesDisplay');
        if (messagesDisplay) {
            messagesDisplay.scrollTop = messagesDisplay.scrollHeight;
        }
    </script>
</body>
</html>
