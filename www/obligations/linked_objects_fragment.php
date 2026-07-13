<?php
// Renders the linked-objects list for an obligation, shared by
// obligations/edit.php (initial render) and obligations/links_eval.php (the
// fragment returned after the modal saves).

/**
 * @param array $linked ObligationManagement::getLinkedObjects() result
 */
function render_linked_objects_list(array $linked): string {
    $views = [
        'assets' => ['Household assets', '/assets/edit.php?id='],
        'documents' => ['Documents', '/documents/edit.php?id='],
        'policies' => ['Insurance policies', '/insurance/edit.php?id='],
        'contacts' => ['Contacts', '/contacts/edit.php?id='],
    ];

    if (!array_filter($linked)) {
        return '<p class="small">Nothing linked yet. Link the assets, documents, policies, and contacts needed to complete this obligation.</p>';
    }

    $html = '<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">';
    foreach ($views as $key => [$label, $urlPrefix]) {
        if (empty($linked[$key])) continue;
        $html .= '<div><strong>' . h($label) . '</strong>';
        foreach ($linked[$key] as $row) {
            $html .= '<div><a href="' . h($urlPrefix . (int)$row['id']) . '">' . h($row['name']) . '</a></div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
