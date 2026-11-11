// Zusicherungen für die Kachel; tile_test.sh hängt die Datei an module.html an.
(function () {
  const out = [];
  const check = (name, ok) => out.push((ok ? 'PASS ' : 'FAIL ') + name);
  const $ = id => document.getElementById(id);
  const seg = k => document.querySelector('#lines g[data-k="' + k + '"]');
  const base = {
    type: 'state',
    w: { pv: 4800, pv1: 3600, pv2: 1200, netz: -1200, speicher: 1500, haus: 2100, wp: 850, heizstab: 0, wallbox: 0, rest: 1250 },
    soc: 68, socWallbox: 80, waerme: 3250, waermeMax: 6500, cop: 4.2, az: 3.9, autarkie: 83, eigenquote: 57,
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
    check('Zwei PV-Anlagen: Summe 4,8 kW darüber, Anlagen 3,6 und 1,2 kW einzeln', $('app').classList.contains('multi') && /^4[.,]8 kW$/.test($('v-pvsum').textContent) && /^3[.,]6 kW$/.test($('v-pv').textContent) && /^1[.,]2 kW$/.test($('v-pv2').textContent));
    const pvd = k => seg(k).children[1].getAttribute('d'), q = s => (s.match(/Q/g) || []).length, y0 = s => s.slice(1).split(/[ LQ]/)[1];
    check('Zwei PV-Anlagen: Linie geht seitlich ab (erst waagerecht), dann mit einer runden Ecke nach unten zum Haus', q(pvd('pv')) === 1 && q(pvd('pv2')) === 1 && y0(pvd('pv')) === pvd('pv').split('L')[1].split('Q')[0].split(' ')[1] && parseFloat(pvd('pv').slice(1)) < parseFloat(pvd('pv2').slice(1)) && seg('pv').dataset.on === '1' && seg('pv2').dataset.on === '1');
    check('Werte: Haus 2,1 kW, WP 850 W', /^2[.,]1 kW$/.test($('v-haus').textContent) && $('v-wp').textContent === '850 W');
    check('Texte aus dem Startzustand', document.querySelector('#n-rest span').textContent === 'Sonstiges');
    check('Animation an: Klasse anim', $('app').classList.contains('anim') || matchMedia('(prefers-reduced-motion: reduce)').matches);
    check('Einspeisung: Linie grün und rückwärts', seg('netz').dataset.role === 'einsp' && seg('netz').dataset.rev === '1' && $('l-netz').textContent === 'Feeding in');
    check('Speicher lädt: vorwärts, Ladestand im Text', seg('speicher').dataset.rev === '0' && $('l-speicher').textContent === 'charging · 68 %');
    check('Verbraucher: WP und Sonstiges fließen, Wallbox ruht', seg('c-wp').dataset.on === '1' && seg('c-rest').dataset.on === '1' && seg('c-wallbox').dataset.on === '0' && $('n-wallbox').classList.contains('idle'));
    const d = k => seg(k).children[1].getAttribute('d');
    const startX = k => parseFloat(d(k).slice(1));
    check('Jeder Verbraucher hat seine eigene Linie ab dem Haus, mit runden Ecken und ohne Sammelschiene', ['wp', 'wallbox', 'heizstab', 'rest'].every(k => (d('c-' + k).match(/Q/g) || []).length === 2) && !seg('trunk'));
    check('Die Linien starten nebeneinander in der Reihenfolge der Verbraucher', startX('c-wp') < startX('c-wallbox') && startX('c-wallbox') < startX('c-heizstab') && startX('c-heizstab') < startX('c-rest'));
    const rate = k => { const a = seg(k).children[1].getAnimations()[0]; return a ? a.playbackRate : +seg(k).dataset.rate; };
    check('Tempo nach Leistung: PV 4,8 kW schneller als Speicher 1,5 kW schneller als WP 850 W', rate('pv') > rate('speicher') && rate('speicher') > rate('c-wp') && rate('pv') > 2 && rate('c-wp') < 1.5);
    check('Tempo liegt an der laufenden Animation an, nicht nur am Attribut', matchMedia('(prefers-reduced-motion: reduce)').matches || seg('pv').children[1].getAnimations().length === 1);
    check('Wallbox: Ladestand des Fahrzeugs als Ring und Text', $('soc-wallbox').getAttribute('stroke-dasharray') === '80 100' && $('d-wallbox').textContent === '80 %' && !$('n-wallbox').classList.contains('nosoc'));
    check('Wärmepumpe: Wärmeleistung 3,25 von 6,5 kW als halber Ring und Text', $('soc-wp').getAttribute('stroke-dasharray') === '50 100' && /^Heat 3[.,]3 kW$/.test($('h-wp').textContent));
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

    send({ waerme: 9000 });
    check('Wärmeleistung über dem Höchstwert: Ring voll, nicht darüber', $('soc-wp').getAttribute('stroke-dasharray') === '100 100');
    send({ socWallbox: null, waerme: null, waermeMax: null });
    check('Wärmepumpe ohne Höchstwert: kein Ring, kein Text', $('n-wp').classList.contains('nosoc') && $('h-wp').textContent === '');
    check('Wallbox ohne Ladestand: kein Ring, kein Text', $('n-wallbox').classList.contains('nosoc') && $('d-wallbox').textContent === '');

    send({ animation: false });
    check('Animation aus: keine Klasse anim', !$('app').classList.contains('anim'));

    send({ knoten: { heizstab: false } });
    check('Drei Verbraucher: der mittlere hängt gerade unter dem Haus, die äußeren laufen um zwei Ecken', !/Q/.test(d('c-wallbox')) && /Q/.test(d('c-wp')) && /Q/.test(d('c-rest')));

    send({ soc: null, knoten: { pv2: false, speicher: false, wp: false, heizstab: false, wallbox: false } });
    check('Eine PV-Anlage: ein Knoten mit der Summe, gerade Linie, keine zweite', !$('app').classList.contains('multi') && /^4[.,]8 kW$/.test($('v-pv').textContent) && !seg('pv2') && !/Q/.test(seg('pv').children[1].getAttribute('d')));
    check('Ohne Speicher: Knoten und Linie fehlen', $('n-speicher').classList.contains('off') && !seg('speicher'));
    check('Ohne Verbraucher: Reihe und Linien fehlen', $('app').classList.contains('nocons') && !seg('c-rest'));

    handleMessage('kein json');
    handleMessage({ type: 'state' });
    check('Kaputte Nachrichten ändern nichts', /^4[.,]8 kW$/.test($('v-pvsum').textContent));
  } catch (e) {
    check('Ausnahme: ' + e.message, false);
  }
  const pre = document.createElement('pre');
  pre.id = 'result';
  pre.textContent = out.join('\n');
  document.body.appendChild(pre);
})();
