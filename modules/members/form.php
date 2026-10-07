<?php
/**
 * File: form.php
 * Module: Members
 * Assigned to: H Muhamadi
 * Status: DONE
 * Description: Member registration and edit form
 */

require_once __DIR__ . '/../../config/functions.php';
require_login();
require_once __DIR__ . '/Member.php';

$isEdit = isset($_GET['id']);
$memberId = $_GET['id'] ?? null;

$values = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'status' => 'active'
];

try {
    if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $member = Member::find($memberId);
        $values['full_name'] = (string) ($member['full_name'] ?? '');
        $values['email'] = (string) ($member['email'] ?? '');
        $values['phone'] = (string) ($member['phone'] ?? '');
        $values['status'] = (string) ($member['status'] ?? 'active');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (array_keys($values) as $field) {
            $posted = $_POST[$field] ?? '';
            if (!is_scalar($posted)) {
                throw new ValidationException('Form inputs must contain text.');
            }
            $values[$field] = trim((string) $posted);
        }

        csrf_verify();

        if ($isEdit) {
            Member::update($memberId, $values);
            set_flash('success', 'Member updated successfully.');
        } else {
            Member::create($values);
            set_flash('success', 'Member registered successfully.');
        }
        redirect(BASE_URL . '/modules/members/list.php');
    }
} catch (Throwable $e) {
    flash_exception($e);
    if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect(BASE_URL . '/modules/members/list.php');
    }
}

$pageTitle = $isEdit ? 'Edit Member' : 'Register Member';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="h3 mb-0"><?= $isEdit ? 'Edit Member' : 'Register New Member' ?></h1>
        <p class="text-muted mb-0 small"><?= $isEdit ? 'Update details for member #' . e($memberId) : 'Fill in the information to create a new member record.' ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/members/list.php">Back to list</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="<?= e(BASE_URL) ?>/modules/members/form.php<?= $isEdit ? '?id=' . rawurlencode((string) $memberId) : '' ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="full_name">Full Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="full_name" name="full_name" type="text"
                               maxlength="150" required value="<?= e($values['full_name']) ?>"
                               placeholder="e.g. Jean Damascene">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="email">Email Address <span class="text-danger">*</span></label>
                        <input class="form-control" id="email" name="email" type="email"
                               maxlength="150" required value="<?= e($values['email']) ?>"
                               placeholder="e.g. member@example.rw">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="phone">Phone Number <span class="text-muted fw-normal">(Optional)</span></label>
                        <input class="form-control" id="phone" name="phone" type="text"
                               maxlength="20" value="<?= e($values['phone']) ?>"
                               placeholder="e.g. +250780000001">
                        <div class="form-text">Between 7 and 15 digits. May include + prefix and spaces.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="status">Membership Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= strtolower($values['status']) === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= strtolower($values['status']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <div class="form-text">Inactive members cannot borrow new books.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit"><?= $isEdit ? 'Save Changes' : 'Register Member' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/members/list.php">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
