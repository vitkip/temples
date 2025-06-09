<?php
// filepath: c:\xampp\htdocs\temples\includes\alert.php
$alert_types = [
    'success' => 'alert-success',
    'error'   => 'alert-danger',
    'warning' => 'alert-warning',
    'info'    => 'alert-info'
];

foreach ($alert_types as $type => $class) {
    if (!empty($_SESSION[$type])): ?>
        <div class="alert <?= $class ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION[$type]) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION[$type]);
    endif;
}
?>