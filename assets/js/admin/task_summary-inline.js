showToast("You do not have permission to access this page.", "error", "Unauthorized");
    setTimeout(() => history.back(), 2000);

window.addEventListener('ps:task_summary', e => {
    const tasks = Array.isArray(e.detail) ? e.detail : [];
    const total = tasks.length;
    const openCnt = tasks.filter(t => t.status === 'open').length;
    const progressCnt = tasks.filter(t => t.status === 'in_progress').length;
    const doneCnt = tasks.filter(t => t.status === 'completed' || t.status === 'closed').length;

    const totalEl = document.getElementById('rt-task-total');
    const openEl = document.getElementById('rt-task-open');
    const progressEl = document.getElementById('rt-task-progress');
    const doneEl = document.getElementById('rt-task-done');
    if (totalEl) totalEl.textContent = String(total);
    if (openEl) openEl.textContent = String(openCnt);
    if (progressEl) progressEl.textContent = String(progressCnt);
    if (doneEl) doneEl.textContent = String(doneCnt);
  });

window.PS_RT_PAGE = 'task_summary';
