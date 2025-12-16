---
title: Өскемен
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
visible: true
process:
    markdown: true
    twig: true
---

Өскемен қаласындағы «Гематология орталығы» ЖШС филиалы 2015 жылы №4 көпсалалы аурухана базасында ашылған.
Бұл шешім қан аурулары бар науқастарға көмек көрсететін мамандандырылған орталықтың интеграцияланған жүйесін көпсалалы ортада сақтауға мүмкіндік берді.

---

**Серікбаев көшесі, 5-ғимарат, 1-блок, 2-қабат**

Тел.: **+7 747 095 5650** (жұмыс күндері сағат 08:00-ден 17:00-ге дейін)

Эл. пошта: vko@hemcenter.kz

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3Acfd37c26d969bdd5ee3dfa9a935592062c6803bdbfe5191f0557539facaa818d&amp;source=constructor" width="100%" height="320" frameborder="0"></iframe>

---

Филиалда ересек науқастарға қан жүйесі аурулары бойынша медициналық көмек көрсетуге арналған барлық қызмет түрлері жүзеге асырылады, оның ішінде жоғары дозалы химиотерапия, халықаралық хаттамаларға сәйкес диагностика жүргізіледі.
Бөлімшеде әр түрлі бейіндегі тар мамандардың кеңестері, зертханалық қызметтер, қажетті аспаптық зерттеулер қолжетімді, сондай-ақ өз реанимация бөлімі бар.

Сонымен қатар, дәл Өскемен қаласында бүгінде Қазақстанда әлі жүргізілмейтін зерттеулерді уақытылы және қолжетімді түрде ұйымдастыру жүйесі жолға қойылған.
Олардың қатарына диагноз қою және ем тиімділігін бақылау үшін жүргізілетін молекулалық-генетикалық тесттер, сондай-ақ бақылаулық иммуногистохимиялық және гистологиялық зерттеулер кіреді.
Осы мақсатта Орталық Ресей мен алыс шетелдің жетекші клиникаларымен серіктестік қатынастар орнатқан.

Филиалдың мамандар құрамында жоғары білікті гематолог дәрігерлер жұмыс істейді.
Филиалды жоғары санатты реаниматолог дәрігер басқарады — ол кардиохирургия орталығында көп жыл еңбек етіп, қан тамырларына қолжетімділікті қамтамасыз ету және шұғыл медициналық көмек көрсету әдістерін толық меңгерген тәжірибелі маман.

[Дәрілік формуляр](../../drug-formulary)

{% set city = 'oskemen' %}
{% set lang = page.language %}

<div class="pricelist-controls" style="margin-bottom:12px;">
  <button id="pl-toggle" type="button">Бағаны көрсету</button>
  <span id="pl-status" style="margin-left:8px; font-size:90%; color:#666;"></span>
</div>

<div id="pricelist-box" hidden>
  <div id="pl-spinner" style="display:none;">Жүктеу…</div>
  <div id="pl-content">
    {{ pricelist_html(city, lang)|raw }}
  </div>
</div>

<script>
(function() {
  var box     = document.getElementById('pricelist-box');
  var btnTgl  = document.getElementById('pl-toggle');
  var btnRef  = document.getElementById('pl-refresh');
  var cont    = document.getElementById('pl-content');
  var spin    = document.getElementById('pl-spinner');
  var status  = document.getElementById('pl-status');
  var city    = {{ city|json_encode|raw }}; // безопасно вставляем значение города

  btnTgl.addEventListener('click', function() {
    if (box.hasAttribute('hidden')) {
      box.removeAttribute('hidden');
      btnTgl.textContent = 'Бағаны жасыру';
    } else {
      box.setAttribute('hidden', '');
      btnTgl.textContent = 'Бағаны көрсету';
    }
    updateRefreshState();
  });

  
  // Инициализация
  updateRefreshState();
})();
</script>