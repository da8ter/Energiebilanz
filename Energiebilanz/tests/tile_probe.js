// Zusicherungen für die Kachel; tile_test.sh hängt die Datei an module.html an.
(function () {
  const out = [];
  const check = (name, ok) => out.push((ok ? 'PASS ' : 'FAIL ') + name);
  const $ = id => document.getElementById(id);
  const seg = k => document.querySelector('#lines g[data-k="' + k + '"]');
  const base = {
    type: 'state',
    w: { pv: 4800, pv1: 3600, pv2: 1200, netz: -1200, speicher: 1500, haus: 2100, wp: 850, heizstab: 0, wallbox: 0, rest: 1250 },
    soc: 68, cop: 4.2, az: 3.9, autarkie: 83, eigenquote: 57,
    tag: { pv: 18.4, haus: 12.6, bezug: 2.1, einspeisung: 7.9, kosten: 0.63, erloes: 0.63, ersparnis: 3.15 },
    knoten: { pv2: true, speicher: true, wp: true, heizstab: true, wallbox: true },
    schwelle: 20, animation: true,
  };
  const send = change => {
    const msg = JSON.parse(JSON.stringify(base));
    Object.keys(change || {}).forEach(k => { msg[k] = msg[k] && typeof msg[k] === 'object' && change[k] ? Object.assign(msg[k], change[k]) : change[k]; });
    handleMessage(msg);
    window.postMessage(JSON.stringify(msg), '*');   // zweiter Zustellweg
  };
  try {
    send({ texts: { Other: 'Sonstiges' } });
    check('Werte: PV 4,8 kW, Haus 2,1 kW, WP 850 W', /^4[.,]8 kW$/.test($('v-pv').textContent) && /^2[.,]1 kW$/.test($('v-haus').textContent) && $('v-wp').textContent === '850 W');
    check('Texte aus dem Startzustand', document.querySelector('#n-rest span').textContent === 'Sonstiges');
    check('Animation an: Klasse anim', $('app').classList.contains('anim') || matchMedia('(prefers-reduced-motion: reduce)').matches);
    check('Einspeisung: Linie grün und rückwärts', seg('netz').dataset.role === 'einsp' && seg('netz').dataset.rev === '1' && $('l-netz').textContent === 'Feeding in');
    check('Speicher lädt: vorwärts, Ladestand im Text', seg('speicher').dataset.rev === '0' && $('l-speicher').textContent === 'charging · 68 %');
    check('Verbraucher: WP und Sonstiges fließen, Wallbox ruht', seg('c-wp').dataset.on === '1' && seg('c-rest').dataset.on === '1' && seg('c-wallbox').dataset.on === '0' && $('n-wallbox').classList.contains('idle'));
    check('Sammelschiene trägt die Summe (2,1 kW, Stufe 2)', seg('trunk').dataset.on === '1' && seg('trunk').dataset.lvl === '2');
    const rate = k => { const a = seg(k).children[1].getAnimations()[0]; return a ? a.playbackRate : +seg(k).dataset.rate; };
    check('Tempo nach Leistung: PV 4,8 kW schneller als Speicher 1,5 kW schneller als WP 850 W', rate('pv') > rate('speicher') && rate('speicher') > rate('c-wp') && rate('pv') > 2 && rate('c-wp') < 1.5);
    check('Tempo liegt an der laufenden Animation an, nicht nur am Attribut', matchMedia('(prefers-reduced-motion: reduce)').matches || seg('pv').children[1].getAnimations().length === 1);
    check('Kennzahlen: Ring 83 %, Ersparnis 3,15 €', $('r-autarkie').getAttribute('stroke-dasharray') === '83 100' && /^3[.,]15 €$/.test($('g-ersparnis').textContent));

    send({ w: { netz: 800, speicher: -900 } });
    check('Bezug: Linie rot und vorwärts', seg('netz').dataset.role === 'bezug' && seg('netz').dataset.rev === '0' && $('n-netz').classList.contains('bezug'));
    check('Tempo folgt dem neuen Wert (Speicher 900 W langsamer als zuvor 1,5 kW)', rate('speicher') < 2);
    send({ w: { netz: 800, speicher: -900, wp: 22, rest: 484 } });
    check('Kleine Leistungen unterscheiden sich deutlich: 484 W mindestens dreimal so schnell wie 22 W', rate('c-rest') >= 3 * rate('c-wp'));
    send({ w: { netz: 800, speicher: -900, wallbox: 10000, rest: 15000, wp: 20000 } });
    check('Anschlag bei 15 kW: 10 kW darunter, 15 kW und 20 kW gleich schnell (4-fach)', rate('c-wallbox') > 3 && rate('c-wallbox') < 4 && rate('c-rest') === 4 && rate('c-wp') === 4);
    check('Speicher entlädt: rückwärts', seg('speicher').dataset.rev === '1' && $('l-speicher').textContent.indexOf('discharging') === 0);

    send({ w: { netz: 10, speicher: 0 } });
    check('Unter der Schwelle: kein Fluss, Knoten ruht', seg('netz').dataset.on === '0' && $('n-netz').classList.contains('idle') && $('l-netz').textContent === 'Grid');

    const lines = $('lines').innerHTML;
    send({ w: { netz: 10, speicher: 0 } });
    check('Gleiche Nachricht noch einmal: Linien bleiben stehen', $('lines').innerHTML === lines);

    send({ animation: false });
    check('Animation aus: keine Klasse anim', !$('app').classList.contains('anim'));

    send({ soc: null, knoten: { speicher: false, wp: false, heizstab: false, wallbox: false } });
    check('Ohne Speicher: Knoten und Linie fehlen', $('n-speicher').classList.contains('off') && !seg('speicher'));
    check('Ohne Verbraucher: Reihe und Schiene fehlen', $('app').classList.contains('nocons') && !seg('trunk'));

    handleMessage('kein json');
    handleMessage({ type: 'state' });
    check('Kaputte Nachrichten ändern nichts', /^4[.,]8 kW$/.test($('v-pv').textContent));
  } catch (e) {
    check('Ausnahme: ' + e.message, false);
  }
  const pre = document.createElement('pre');
  pre.id = 'result';
  pre.textContent = out.join('\n');
  document.body.appendChild(pre);
})();
