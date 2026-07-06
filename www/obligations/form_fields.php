<?php
// Shared form fields for obligations/add.php and obligations/edit.php.
// Renders the full field set (with the per-recurrence-type show/hide JS);
// the surrounding page provides the <form>, CSRF, and buttons.

/**
 * @param array $v     Current field values (raw strings; annual_month/annual_day split out)
 * @param array $opts  ['users' => rows, 'assets' => rows, 'documents' => rows,
 *                      'policies' => rows, 'contacts' => rows]
 */
function render_obligation_form_fields(array $v, array $opts): void {
    $users = $opts['users'] ?? [];
    $type = (string)($v['recurrence_type'] ?? 'date_of_year');
?>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Title
        <input type="text" name="title" value="<?=h($v['title'] ?? '')?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($v['category'] ?? '')?>" list="categorySuggestions">
        <datalist id="categorySuggestions">
          <?php foreach (ObligationManagement::CATEGORY_SUGGESTIONS as $c): ?>
            <option value="<?=h($c)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </label>
    </div>

    <label>Description / Instructions
      <textarea name="description" rows="3" placeholder="What needs to happen, account numbers, where to go…"><?=h($v['description'] ?? '')?></textarea>
    </label>

    <h3>Schedule</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Repeats
        <select name="recurrence_type" id="recurrenceType">
          <option value="does_not_repeat" <?= $type === 'does_not_repeat' ? 'selected' : '' ?>>Does not repeat</option>
          <option value="every_n_days" <?= $type === 'every_n_days' ? 'selected' : '' ?>>Every N days</option>
          <option value="every_n_weeks" <?= $type === 'every_n_weeks' ? 'selected' : '' ?>>Every N weeks</option>
          <option value="every_n_months" <?= $type === 'every_n_months' ? 'selected' : '' ?>>Every N months</option>
          <option value="every_n_years" <?= $type === 'every_n_years' ? 'selected' : '' ?>>Every N years</option>
          <option value="day_of_month" <?= $type === 'day_of_month' ? 'selected' : '' ?>>Specific day each month</option>
          <option value="date_of_year" <?= $type === 'date_of_year' ? 'selected' : '' ?>>Specific date each year</option>
          <option value="after_completion" <?= $type === 'after_completion' ? 'selected' : '' ?>>N days/weeks/months after last completion</option>
        </select>
      </label>

      <label data-show-for="does_not_repeat">
        Due date
        <input type="date" name="anchor_date_once" value="<?=h($v['anchor_date_once'] ?? ($v['anchor_date'] ?? ''))?>">
        <small class="small">One-time obligation — it will not come back after completion.</small>
      </label>

      <label data-show-for="every_n_days every_n_weeks every_n_months every_n_years after_completion">
        Repeat every (N)
        <input type="number" name="recurrence_interval" value="<?=h($v['recurrence_interval'] ?? '1')?>" min="1" step="1">
      </label>

      <label data-show-for="after_completion">
        Unit
        <select name="recurrence_unit">
          <option value="days" <?= ($v['recurrence_unit'] ?? '') === 'days' ? 'selected' : '' ?>>Days</option>
          <option value="weeks" <?= ($v['recurrence_unit'] ?? '') === 'weeks' ? 'selected' : '' ?>>Weeks</option>
          <option value="months" <?= ($v['recurrence_unit'] ?? '') === 'months' ? 'selected' : '' ?>>Months</option>
        </select>
      </label>

      <label data-show-for="day_of_month">
        Day of month (1–31)
        <input type="number" name="day_of_month" value="<?=h($v['day_of_month'] ?? '')?>" min="1" max="31" step="1">
        <small class="small">Day 31 falls on the last day of shorter months.</small>
      </label>

      <label data-show-for="date_of_year">
        Month
        <select name="annual_month">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= (int)($v['annual_month'] ?? 0) === $m ? 'selected' : '' ?>><?= date('F', mktime(12, 0, 0, $m, 1, 2000)) ?></option>
          <?php endfor; ?>
        </select>
      </label>

      <label data-show-for="date_of_year">
        Day
        <input type="number" name="annual_day" value="<?=h($v['annual_day'] ?? '')?>" min="1" max="31" step="1">
      </label>

      <label data-show-for="every_n_days every_n_weeks every_n_months every_n_years">
        Start date (first due)
        <input type="date" name="anchor_date" value="<?=h($v['anchor_date'] ?? '')?>">
      </label>

      <label data-show-for="after_completion">
        First due date (optional)
        <input type="date" name="anchor_date_after" value="<?=h($v['anchor_date_after'] ?? ($v['anchor_date'] ?? ''))?>">
        <small class="small">Leave blank to schedule N days/weeks/months from today.</small>
      </label>
    </div>

    <h3>Ownership &amp; Reminders</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Responsible person
        <select name="responsible_user_id">
          <option value="">— Unassigned (admins get reminders) —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($v['responsible_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?=h(trim($u['first_name'] . ' ' . $u['last_name']))?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Applies to
        <select name="applies_to_user_id">
          <option value="">Entire family</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($v['applies_to_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?=h(trim($u['first_name'] . ' ' . $u['last_name']))?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Reminder lead time (days before due)
        <input type="number" name="reminder_lead_days" value="<?=h($v['reminder_lead_days'] ?? '7')?>" min="0" step="1">
      </label>
      <label class="inline" style="align-self:end;">
        <input type="checkbox" name="is_active" value="1" <?= !empty($v['is_active']) ? 'checked' : '' ?>>
        Active
      </label>
    </div>

    <h3>Linked Objects</h3>
    <p class="small">Attach the records needed to complete this obligation (hold Cmd/Ctrl to select multiple).</p>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <?php
        $linkGroups = [
            ['label' => 'Household assets', 'name' => 'link_assets', 'rows' => $opts['assets'] ?? [], 'selected' => $v['linked_asset_ids'] ?? [], 'nameKey' => 'name'],
            ['label' => 'Documents', 'name' => 'link_documents', 'rows' => $opts['documents'] ?? [], 'selected' => $v['linked_document_ids'] ?? [], 'nameKey' => 'title'],
            ['label' => 'Insurance policies', 'name' => 'link_policies', 'rows' => $opts['policies'] ?? [], 'selected' => $v['linked_policy_ids'] ?? [], 'nameKey' => 'name'],
            ['label' => 'Contacts', 'name' => 'link_contacts', 'rows' => $opts['contacts'] ?? [], 'selected' => $v['linked_contact_ids'] ?? [], 'nameKey' => 'name'],
        ];
      ?>
      <?php foreach ($linkGroups as $g): ?>
        <label><?=h($g['label'])?>
          <select name="<?=h($g['name'])?>[]" multiple size="4">
            <?php foreach ($g['rows'] as $row): ?>
              <option value="<?= (int)$row['id'] ?>" <?= in_array((int)$row['id'], array_map('intval', (array)$g['selected']), true) ? 'selected' : '' ?>>
                <?=h($row[$g['nameKey']])?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endforeach; ?>
    </div>

    <script>
      (function(){
        // Show only the schedule fields relevant to the chosen recurrence type
        var typeSelect = document.getElementById('recurrenceType');
        var conditionals = document.querySelectorAll('[data-show-for]');
        function sync() {
          var type = typeSelect.value;
          conditionals.forEach(function(el) {
            var show = el.getAttribute('data-show-for').split(' ').indexOf(type) !== -1;
            el.style.display = show ? '' : 'none';
          });
        }
        typeSelect.addEventListener('change', sync);
        sync();
      })();
    </script>
<?php
}

// Extract obligation form values from a POST payload (for eval pages),
// composing annual_month/annual_day into annual_month_day and picking the
// right anchor_date field for the chosen recurrence type.
function obligation_data_from_post(array $post): array {
    $data = $post;
    $type = (string)($post['recurrence_type'] ?? '');

    if ($type === 'date_of_year') {
        $month = (int)($post['annual_month'] ?? 0);
        $day = (int)($post['annual_day'] ?? 0);
        $data['annual_month_day'] = sprintf('%02d-%02d', $month, $day);
    }
    if ($type === 'after_completion') {
        $data['anchor_date'] = trim((string)($post['anchor_date_after'] ?? ''));
    }
    if ($type === 'does_not_repeat') {
        $data['anchor_date'] = trim((string)($post['anchor_date_once'] ?? ''));
    }
    return $data;
}

// Links arrays from a POST payload, in the shape ObligationManagement expects.
function obligation_links_from_post(array $post): array {
    return [
        'assets' => (array)($post['link_assets'] ?? []),
        'documents' => (array)($post['link_documents'] ?? []),
        'policies' => (array)($post['link_policies'] ?? []),
        'contacts' => (array)($post['link_contacts'] ?? []),
    ];
}
