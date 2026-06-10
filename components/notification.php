<?php
// This component displays success or error messages stored in session.
// It should be included at the top of any page where you want to show notifications.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

?>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php
    // Handle Success Messages
    if (isset($_SESSION['success_message'])): 
    ?>
        Swal.fire({
            title: 'Success!',
            text: <?php echo json_encode($_SESSION['success_message']); ?>,
            icon: 'success',
            confirmButtonColor: 'red'
        });
        <?php unset($_SESSION['success_message']); ?>
    <?php 
    endif; 

    // Handle Error Messages
    if (isset($_SESSION['error_message'])): 
    ?>
        Swal.fire({
            title: 'Error!',
            text: <?php echo json_encode($_SESSION['error_message']); ?>,
            icon: 'error',
            confirmButtonColor: '#333'
        });
        <?php unset($_SESSION['error_message']); ?>
    <?php 
    endif; 
    ?>
});
</script>