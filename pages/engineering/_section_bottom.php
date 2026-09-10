        </div>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var pairs = [['usersToggle', 'usersGroup'], ['devToggle', 'devGroup'], ['maintenanceToggle', 'maintenanceGroup']];
      pairs.forEach(function (p) {
        var btn = document.getElementById(p[0]);
        var grp = document.getElementById(p[1]);
        if (btn && grp) {
          btn.addEventListener('click', function () { grp.classList.toggle('open'); });
        }
      });
    })();
  </script>
  <script src="../../assets/js/mobile-menu.js"></script>
  <script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
