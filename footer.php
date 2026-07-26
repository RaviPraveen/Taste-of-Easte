  </main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function toggleCollapse() {
  const collapsed = document.body.classList.toggle('sb-collapsed');
  localStorage.setItem('sbCollapsed', collapsed ? '1' : '0');
}
// Toast notifications
document.querySelectorAll('.toast').forEach((el) => new bootstrap.Toast(el, { delay: 3500 }).show());
// Session sync: if the login changes in another tab (different user, role, or
// logout), this tab reloads so it never shows a stale account's screen.
const sessionUser = document.body.dataset.sessionUser || '';
async function checkSession() {
  try {
    const res = await fetch('api/whoami.php', { cache: 'no-store' });
    const u = await res.json();
    if ((u.id + ':' + u.role) !== sessionUser) location.reload();
  } catch (e) { /* server briefly unreachable — check again next tick */ }
}
setInterval(checkSession, 15000);
window.addEventListener('focus', checkSession);
// Live clock in the topbar
const clockEl = document.getElementById('liveClock');
if (clockEl) {
  const tickClock = () => {
    clockEl.textContent = new Date().toLocaleTimeString('en-US', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
    });
  };
  tickClock();
  setInterval(tickClock, 1000);
}
// Animated counters: <span data-countup="1234.5" data-decimals="2" data-prefix="Rs. ">
document.querySelectorAll('[data-countup]').forEach((el) => {
  const target = parseFloat(el.dataset.countup) || 0;
  const decimals = parseInt(el.dataset.decimals || '0', 10);
  const prefix = el.dataset.prefix || '';
  const start = performance.now();
  const dur = 750;
  function tick(now) {
    const p = Math.min(1, (now - start) / dur);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = prefix + (target * eased).toLocaleString(undefined, {
      minimumFractionDigits: decimals, maximumFractionDigits: decimals,
    });
    if (p < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
});
</script>
</body>
</html>
