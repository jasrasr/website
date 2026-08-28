document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('entriesTable');
  if (!table) return;

  table.querySelectorAll('tr').forEach((row) => {
    const cells = row.children;
    if (cells.length < 4) return;
    const plateCell = Array.from(cells).find((cell) => cell.classList.contains('col-plate') || cell.classList.contains('entry-plate'));
    if (plateCell && cells[1] !== plateCell) row.insertBefore(plateCell, cells[1]);
  });

  table.querySelectorAll('tbody tr').forEach((row) => {
    const actionCell = row.querySelector('td:last-child');
    if (!actionCell) return;
    actionCell.classList.add('compact-action-cell');
    actionCell.querySelectorAll('button, a').forEach((control) => {
      const label = (control.textContent || '').trim();
      const lower = label.toLowerCase();
      let icon = '•';
      if (lower.includes('more')) icon = 'ⓘ';
      else if (lower.includes('re-process') || lower.includes('reprocess')) icon = '↻';
      else if (lower.includes('edit')) icon = '✎';
      else if (lower.includes('delete')) icon = '🗑';
      control.dataset.fullLabel = label;
      control.textContent = icon;
      control.title = label;
      control.setAttribute('aria-label', label);
      control.classList.add('compact-row-action');
    });
  });

  const pendingSelection = JSON.parse(sessionStorage.getItem('duplicateCleanupSelection') || '[]');
  if (Array.isArray(pendingSelection) && pendingSelection.length) {
    let restored = 0;
    pendingSelection.forEach((id) => {
      const checkbox = document.querySelector(`.delete-select[data-id="${CSS.escape(String(id))}"]`);
      if (checkbox) {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        restored++;
      }
    });
    sessionStorage.removeItem('duplicateCleanupSelection');
    if (restored) setTimeout(() => alert(`${restored} duplicate record(s) are selected for review and deletion. No records were deleted automatically.`), 100);
  }

  const bulk = document.querySelector('.bulk-actions');
  if (!bulk || document.getElementById('cleanUpDuplicates')) return;
  const button = document.createElement('button');
  button.type = 'button';
  button.id = 'cleanUpDuplicates';
  button.className = 'success duplicate-cleanup-button';
  button.textContent = 'Clean Up Duplicates';
  button.addEventListener('click', async () => {
    button.disabled = true;
    const original = button.textContent;
    button.textContent = 'Analyzing…';
    try {
      const previewResponse = await fetch('cleanup_duplicates.php');
      const preview = await previewResponse.json();
      if (!previewResponse.ok || preview.error) throw new Error(preview.error || 'Unable to analyze duplicates.');
      if (!preview.groups || preview.groups.length === 0) {
        alert('No duplicate plate groups were found.');
        return;
      }
      const duplicateCount = preview.groups.reduce((sum, group) => sum + group.remove_count, 0);
      if (!confirm(`Found ${preview.groups.length} duplicate plate group(s). Metadata will be merged into the best entry and ${duplicateCount} remaining record(s) will be selected for deletion. Continue?`)) return;

      button.textContent = 'Merging…';
      const form = new FormData();
      form.append('action', 'merge');
      const mergeResponse = await fetch('cleanup_duplicates.php', { method: 'POST', body: form });
      const result = await mergeResponse.json();
      if (!mergeResponse.ok || result.error) throw new Error(result.error || 'Duplicate cleanup failed.');
      sessionStorage.setItem('duplicateCleanupSelection', JSON.stringify(result.selected_ids || []));
      alert(`Merged metadata for ${result.groups_processed} plate group(s). The remaining duplicates will be selected after the page refresh.`);
      window.location.reload();
    } catch (error) {
      alert(error.message || String(error));
      button.disabled = false;
      button.textContent = original;
    }
  });
  bulk.appendChild(button);
});
