/*
 * GameMGR status widget.
 *
 * For somebody who wants the status inside their own page rather than in an
 * iframe: no styling of ours lands on them, so the markup is deliberately plain
 * and every element carries a class they can target.
 *
 *   <div id="status"></div>
 *   <script src="https://panel.example.com/js/status-widget.js"
 *           data-status-url="https://panel.example.com/status/my-server.json"
 *           data-target="#status"></script>
 *
 * One script tag, no build step, no dependencies, and nothing global left
 * behind. It reads its own <script> element for configuration, which is what
 * lets the same static file serve every status page on the install.
 *
 * Everything is written with textContent. The data is the server owner's own,
 * but it travels through a JSON endpoint into a stranger's page, and building
 * HTML out of a name somebody typed is how a status widget becomes an XSS.
 */
(function () {
  'use strict';

  var script = document.currentScript;
  if (!script) return;

  var url = script.getAttribute('data-status-url');
  if (!url) return;

  var target = script.getAttribute('data-target');
  var refresh = parseInt(script.getAttribute('data-refresh') || '60', 10);

  // Where to draw. An explicit target wins; otherwise the widget replaces
  // itself where the script tag sits, which is what somebody pasting one line
  // into a page expects to happen.
  var mount = target ? document.querySelector(target) : null;
  if (!mount) {
    mount = document.createElement('div');
    script.parentNode.insertBefore(mount, script);
  }
  mount.className = 'gamemgr-status';

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function row(label, value) {
    var line = el('div', 'gamemgr-status__row');
    line.appendChild(el('span', 'gamemgr-status__label', label));
    line.appendChild(el('span', 'gamemgr-status__value', value));
    return line;
  }

  function render(data) {
    mount.textContent = '';

    var head = el('div', 'gamemgr-status__head');
    head.appendChild(el('span', 'gamemgr-status__name', data.name || 'Server'));
    head.appendChild(el(
      'span',
      'gamemgr-status__state gamemgr-status__state--' + (data.online ? 'online' : 'offline'),
      data.online ? 'Online' : 'Offline'
    ));
    mount.appendChild(head);

    var body = el('div', 'gamemgr-status__body');

    // Absent means the owner switched it off on their status page, so nothing
    // is drawn for it rather than an empty row.
    if (data.connect || data.address) body.appendChild(row('Connect', data.connect || data.address));

    if (data.players) {
      body.appendChild(row(
        'Players',
        data.players.max ? data.players.online + ' / ' + data.players.max : String(data.players.online)
      ));
    }

    if (data.running) body.appendChild(row('Running', data.running));

    mount.appendChild(body);
  }

  function load() {
    fetch(url, { credentials: 'omit', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(render)
      .catch(function () {
        // A panel that is unreachable is not the host site's problem and must
        // not put an error in the middle of their page. If nothing has been
        // drawn yet, say the one useful thing and stop.
        if (!mount.firstChild) {
          mount.appendChild(el('div', 'gamemgr-status__error', 'Status unavailable'));
        }
      });
  }

  load();

  // Zero turns polling off. The floor is 15 seconds because the endpoint is
  // cached for 30 and hammering past that only costs the host site battery.
  if (refresh > 0) setInterval(load, Math.max(15, refresh) * 1000);
})();
