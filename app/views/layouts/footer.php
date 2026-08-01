<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const storagePrefix = 'terranova.sidebar.';
        document.querySelectorAll('[data-security-menu-collapse]').forEach(function(section) {
            const key = section.getAttribute('data-security-menu-collapse');
            const button = document.querySelector('[data-bs-target="#' + section.id + '"]');
            if (!key || !button) return;

            try {
                if (!button.classList.contains('active') && localStorage.getItem(storagePrefix + key) === 'open') {
                    bootstrap.Collapse.getOrCreateInstance(section, { toggle: false }).show();
                }
            } catch (error) {
                // El menú sigue funcionando aunque el navegador bloquee localStorage.
            }

            section.addEventListener('shown.bs.collapse', function() {
                try { localStorage.setItem(storagePrefix + key, 'open'); } catch (error) {}
            });
            section.addEventListener('hidden.bs.collapse', function() {
                try { localStorage.setItem(storagePrefix + key, 'closed'); } catch (error) {}
            });
        });
    });

    (function() {
        const latEl = document.getElementById('latitude');
        const lngEl = document.getElementById('longitude');

        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                latEl.value = pos.coords.latitude;
                lngEl.value = pos.coords.longitude;
            },
            (err) => {
                // Si el usuario niega permisos, se enviará vacío y el backend guardará NULL.
                console.log('Geolocalización no disponible:', err.message);
            }, {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 30000
            }
        );
    })();
</script>

</body>

</html>
