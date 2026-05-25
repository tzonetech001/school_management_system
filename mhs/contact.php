<?php
// contact.php - Comprehensive Contact Page with Background Image
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/contact.css";

include 'header.php';

// ==================== PROCESS CONTACT FORM ====================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    // Get and sanitize input
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $phone_number = mysqli_real_escape_string($conn, trim($_POST['phone_number'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($phone_number)) $errors[] = "Phone number is required";
    if (empty($message)) $errors[] = "Message is required";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    
    if (empty($errors)) {
        $insert_sql = "INSERT INTO contact_messages (full_name, phone_number, email, subject, message, ip_address, status) 
                       VALUES ('$full_name', '$phone_number', '$email', '$subject', '$message', '$ip_address', 'unread')";
        
        if (mysqli_query($conn, $insert_sql)) {
            $success_message = "Thank you! Your message has been sent successfully. We will get back to you soon.";
            // Clear form
            $_POST = array();
        } else {
            $error_message = "Sorry, there was an error sending your message. Please try again later.";
        }
    } else {
        $error_message = implode(", ", $errors);
    }
}

// ==================== GET CONTACT INFORMATION FROM DATABASE ====================

// Get Head Master
$head_master_sql = "SELECT a.*, ar.role_name 
                    FROM admins a
                    JOIN admin_role_assignments ara ON a.id = ara.admin_id
                    JOIN admin_roles ar ON ara.role_id = ar.id
                    WHERE (ar.role_name LIKE '%Head Master%' OR ar.role_name LIKE '%Headteacher%')
                    AND a.status = 1
                    ORDER BY ara.is_primary DESC
                    LIMIT 1";
$head_master_result = mysqli_query($conn, $head_master_sql);
$head_master = mysqli_fetch_assoc($head_master_result);

// Get Academic Master
$academic_master_sql = "SELECT a.*, ar.role_name 
                         FROM admins a
                         JOIN admin_role_assignments ara ON a.id = ara.admin_id
                         JOIN admin_roles ar ON ara.role_id = ar.id
                         WHERE (ar.role_name LIKE '%Academic%' OR ar.role_name LIKE '%Academic Master%')
                         AND a.status = 1
                         ORDER BY ara.is_primary DESC
                         LIMIT 1";
$academic_master_result = mysqli_query($conn, $academic_master_sql);
$academic_master = mysqli_fetch_assoc($academic_master_result);

// Get Second Master / Deputy Head
$second_master_sql = "SELECT a.*, ar.role_name 
                       FROM admins a
                       JOIN admin_role_assignments ara ON a.id = ara.admin_id
                       JOIN admin_roles ar ON ara.role_id = ar.id
                       WHERE (ar.role_name LIKE '%Second%' OR ar.role_name LIKE '%Deputy%' OR ar.role_name LIKE '%Assistant%')
                       AND a.status = 1
                       ORDER BY ara.is_primary DESC
                       LIMIT 1";
$second_master_result = mysqli_query($conn, $second_master_sql);
$second_master = mysqli_fetch_assoc($second_master_result);

// Get Bursar/Finance Officer (optional)
$bursar_sql = "SELECT a.*, ar.role_name 
                FROM admins a
                JOIN admin_role_assignments ara ON a.id = ara.admin_id
                JOIN admin_roles ar ON ara.role_id = ar.id
                WHERE (ar.role_name LIKE '%Bursar%' OR ar.role_name LIKE '%Finance%')
                AND a.status = 1
                ORDER BY ara.is_primary DESC
                LIMIT 1";
$bursar_result = mysqli_query($conn, $bursar_sql);
$bursar = mysqli_fetch_assoc($bursar_result);
?>

<main class="main-content">
    
    <!-- HERO SECTION -->
    <section class="contact-hero">
        <div class="hero-content">
            <span class="hero-badge">
                <i class="fas fa-phone-alt"></i> Get in Touch
            </span>
            <h1>Contact Us</h1>
            <p>We're here to answer your questions and welcome you to the Muyovozi family.</p>
        </div>
    </section>

    <div class="container">
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Two Column Layout -->
        <div class="contact-wrapper">
            
            <!-- LEFT COLUMN - Contact Information -->
            <div class="contact-info">
                <h2>School Administration</h2>
                <p>Reach out to our dedicated team for any inquiries</p>
                
                <!-- Head Master Card -->
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div>
                            <h3><?php echo $head_master ? htmlspecialchars($head_master['first_name'] . ' ' . $head_master['last_name']) : 'Head Master'; ?></h3>
                            <span class="info-role">Head Master</span>
                        </div>
                    </div>
                    <div class="info-details">
                        <?php if ($head_master && !empty($head_master['phone_number'])): ?>
                            <div class="detail-row">
                                <i class="fas fa-phone-alt"></i>
                                <a href="tel:<?php echo htmlspecialchars($head_master['phone_number']); ?>">
                                    <?php echo htmlspecialchars($head_master['phone_number']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        
                    </div>
                </div>
                
                <!-- Academic Master Card -->
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-icon">
                            <i class="fas fa-chalkboard-user"></i>
                        </div>
                        <div>
                            <h3><?php echo $academic_master ? htmlspecialchars($academic_master['first_name'] . ' ' . $academic_master['last_name']) : 'Academic Master'; ?></h3>
                            <span class="info-role">Academic Master</span>
                        </div>
                    </div>
                    <div class="info-details">
                        <?php if ($academic_master && !empty($academic_master['phone_number'])): ?>
                            <div class="detail-row">
                                <i class="fas fa-phone-alt"></i>
                                <a href="tel:<?php echo htmlspecialchars($academic_master['phone_number']); ?>">
                                    <?php echo htmlspecialchars($academic_master['phone_number']); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="detail-row">
                                <i class="fas fa-phone-alt"></i>
                                <span>+255 622 032 539</span>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <!-- Second Master Card -->
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div>
                            <h3><?php echo $second_master ? htmlspecialchars($second_master['first_name'] . ' ' . $second_master['last_name']) : 'Second Master'; ?></h3>
                            <span class="info-role">Second Master</span>
                        </div>
                    </div>
                    <div class="info-details">
                        <?php if ($second_master && !empty($second_master['phone_number'])): ?>
                            <div class="detail-row">
                                <i class="fas fa-phone-alt"></i>
                                <a href="tel:<?php echo htmlspecialchars($second_master['phone_number']); ?>">
                                    <?php echo htmlspecialchars($second_master['phone_number']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bursar Card (Optional) -->
                <?php if ($bursar): ?>
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($bursar['first_name'] . ' ' . $bursar['last_name']); ?></h3>
                            <span class="info-role">School Bursar</span>
                        </div>
                    </div>
                    <div class="info-details">
                        <?php if (!empty($bursar['phone_number'])): ?>
                            <div class="detail-row">
                                <i class="fas fa-phone-alt"></i>
                                <a href="tel:<?php echo htmlspecialchars($bursar['phone_number']); ?>">
                                    <?php echo htmlspecialchars($bursar['phone_number']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
              
            </div>
            
            <!-- RIGHT COLUMN - Contact Form -->
            <div class="contact-form-container">
                <h2>Send Us a Message</h2>
                <p>Fill out the form below and we'll get back to you as soon as possible.</p>
                
                <form method="POST" action="" class="contact-form" id="contactForm">
                    <div class="form-group">
                        <label for="full_name">
                            <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                        </label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                               placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone_number">
                                <i class="fas fa-phone"></i> Phone Number <span class="required">*</span>
                            </label>
                            <input type="tel" id="phone_number" name="phone_number" 
                                   value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                   placeholder="Enter your phone number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="your@email.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">
                            <i class="fas fa-tag"></i> Subject
                        </label>
                        <input type="text" id="subject" name="subject" 
                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                               placeholder="What is this regarding?">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">
                            <i class="fas fa-comment"></i> Message <span class="required">*</span>
                        </label>
                        <textarea id="message" name="message" rows="6" 
                                  placeholder="Type your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="submit_contact" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
                
            </div>
        </div>
        
        <!-- Map Section -->
        <div class="map-section">
            <h3><i class="fas fa-map-marked-alt"></i> Our Location</h3>
            <p>Kambi ya Mtabila / Muyovosi, Kasulu District, Kigoma Region, Tanzania</p>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15921.789456123!2d30.26763!3d-4.41209!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNMKwMjQnNDMuNSJTIDMwwrAxNicwMy41IkU!5e1!3m2!1sen!2stz!4v1623456789012!5m2!1sen!2stz"
                    allowfullscreen="" 
                    loading="lazy"
                    title="Muyovozi High School Location">
                </iframe>
            </div>
            <div class="coordinates">
                <span class="coord" onclick="copyCoordinate('-4.41209')">
                    <i class="fas fa-map-marker-alt"></i> -4.41209
                </span>
                <span class="coord" onclick="copyCoordinate('30.26763')">
                    <i class="fas fa-map-marker-alt"></i> 30.26763
                </span>
            </div>
        </div>
        
        <!-- Emergency Contact -->
        <div class="emergency-section">
            <div class="emergency-card">
                <i class="fas fa-phone-alt"></i>
                <h4>Emergency Contact</h4>
                <div class="emergency-number">+255 619844080</div>
                <p>Available 24/7 for urgent matters</p>
            </div>
            <div class="emergency-card">
                <i class="fas fa-envelope"></i>
                <h4>Support Email</h4>
                <div class="emergency-number">support@muyovozihigh.sc.tz</div>
                <p>For technical support and inquiries</p>
            </div>
        </div>
        
    </div>
</main>

<script>
// Copy coordinate function
function copyCoordinate(coord) {
    navigator.clipboard.writeText(coord).then(() => {
        // Show notification
        const notification = document.createElement('div');
        notification.className = 'copy-notification';
        notification.innerHTML = '<i class="fas fa-check-circle"></i> Coordinate copied!';
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.remove();
        }, 2000);
    });
}

// Form validation before submit
document.getElementById('contactForm')?.addEventListener('submit', function(e) {
    const fullName = document.getElementById('full_name').value.trim();
    const phone = document.getElementById('phone_number').value.trim();
    const message = document.getElementById('message').value.trim();
    
    if (!fullName) {
        e.preventDefault();
        alert('Please enter your full name');
        return false;
    }
    if (!phone) {
        e.preventDefault();
        alert('Please enter your phone number');
        return false;
    }
    if (!message) {
        e.preventDefault();
        alert('Please enter your message');
        return false;
    }
    return true;
});
</script>

<?php include '../controller/footer.php'; ?>