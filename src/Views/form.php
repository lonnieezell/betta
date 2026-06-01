<?php

/**
 * @var array<\Myth\Betta\Enums\CategoryEnum> $categories
 */
?>
<div class="betta-feedback-form">
    <?php if (session()->has('feedback_success')): ?>
        <p class="betta-success"><?= esc(session('feedback_success')) ?></p>
    <?php endif ?>

    <?php if (session()->has('errors')): ?>
        <ul class="betta-errors">
            <?php foreach (session('errors') as $field => $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <div class="betta-success-message" style="display:none"></div>

    <?= form_open('feedback/submit', ['id' => 'betta-form']) ?>

        <input type="hidden" name="url_context" value="">

        <div>
            <label for="betta-category">Category</label>
            <select id="betta-category" name="category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= esc($cat->value) ?>"
                        <?= set_select('category', $cat->value) ?>>
                        <?= esc(ucfirst($cat->value)) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div>
            <label for="betta-message">Message <span aria-hidden="true">*</span></label>
            <textarea id="betta-message" name="message" required><?= set_value('message') ?></textarea>
            <span data-error="message"></span>
        </div>

        <div>
            <label for="betta-email">Email (optional)</label>
            <input type="email" id="betta-email" name="email" value="<?= set_value('email') ?>">
            <span data-error="email"></span>
        </div>

        <button type="submit">Send Feedback</button>

    <?= form_close() ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var urlField = document.querySelector('[name="url_context"]');
    if (urlField) {
        urlField.value = window.location.href;
    }

    var form = document.getElementById('betta-form');
    if (!form) { return; }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(form);
        var successDiv = form.closest('.betta-feedback-form').querySelector('.betta-success-message');

        // Clear previous errors
        form.querySelectorAll('[data-error]').forEach(function (el) {
            el.textContent = '';
        });

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        })
        .then(function (result) {
            if (result.ok) {
                form.style.display = 'none';
                successDiv.textContent = 'Thank you for your feedback!';
                successDiv.style.display = '';
            } else {
                var errors = result.data.errors || {};
                Object.keys(errors).forEach(function (field) {
                    var span = form.querySelector('[data-error="' + field + '"]');
                    if (span) { span.textContent = errors[field]; }
                });
            }
        });
    });
});
</script>
