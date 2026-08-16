---
title: Астана
branch_address: 'Астана қ., Кенесары көшесі, 4Б'
branch_phone: '+7 771 900 08 64'
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
visible: true
process:
    markdown: true
    twig: true
---

«Гематология орталығы» ЖШС-нің Астана қаласындағы медициналық бөлімшесі 2021 жылы ашылған.
Негізгі мақсаты – ересек тұрғындарды сапалы консультативтік және диагностикалық медициналық қызметтермен қамтамасыз ету.

Гематологтың консультациясы бетпе-бет қабылдау түрінде де, сондай-ақ қашықтан (сырттай және нақты уақыттағы онлайн-консультациялар түрінде) да қолжетімді.

---

Бетпе-бет қабылдау мекенжайы:

**Z05T8A8 Астана қ., Кенесары көшесі, 4Б үй. (жұмыс күндері сағат 08:00-ден 17:00-ге дейін)**

Қабылдауға жазылу телефондары / Whatsapp арқылы:
**📞 +7 771 900 08 64, +7 777 532 65 15**

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3A15e911218eabcae60fbd8a15dfbdee1e87ea189f6d3c2bd1ec323a0319277c11&amp;source=constructor" width="100%" height="240" frameborder="0"></iframe>

---

Консультативтік қабылдаудан бөлек, бөлімшенің өндірістік базасында диагностикалық зерттеулер, соның ішінде зертханалық талдаулар жүргізіледі:
* Сүйек кемігінің трепанобиопсиясы;
* Сүйек кемігін пункциялау, миелограмма санау және цитохимиялық зерттеу;
* Сүйек кемігі мен лимфа түйіндерінің гистологиялық және иммуногистохимиялық зерттеуі;
* Сүйек кемігінің цитогенетикалық зерттеуі;
* Сүйек кемігі мен перифериялық қанды иммунфенотиптеу;
* Кеңейтілген маркерлер панелімен сүйек кемігі мен перифериялық қанның молекулалық-генетикалық зерттеуі.


[Дәрілік формуляр](../../drug-formulary)

{% set city = 'astana' %}
{% set lang = page.language %}

<div class="pricelist-controls">
  <button id="pl-toggle" class="button button--ghost" type="button">Бағаны көрсету</button>
</div>

<div id="pricelist-box" class="pricelist" hidden>
  <div id="pl-content">
    {{ pricelist_html(city, lang)|raw }}
  </div>
</div>

<script>
(function() {
  var box     = document.getElementById('pricelist-box');
  var btnTgl  = document.getElementById('pl-toggle');
  var cont    = document.getElementById('pl-content');
  var city    = {{ city|json_encode|raw }}; // безопасно вставляем значение города

  btnTgl.addEventListener('click', function() {
    if (box.hasAttribute('hidden')) {
      box.removeAttribute('hidden');
      btnTgl.textContent = 'Бағаны жасыру';
    } else {
      box.setAttribute('hidden', '');
      btnTgl.textContent = 'Бағаны көрсету';
    }
  });

})();
</script>