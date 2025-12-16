---
title: Усть-Каменогорск
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
process:
    markdown: true
    twig: true
visible: true
---

Филиал в городе Усть-Каменогорске был открыт 2015 году на базе многопрофильной больницы №4, что позволило сохранить интегрированность специализированного центра для оказания помощи больным с заболеваниями крови в многопрофильном контенте.

---

**ул. Серикбаева, здание 5, корпус 1, второй этаж**

тел. **+7 747 095 5650** (будни с 08:00 до 17:00)

vko@hemcenter.kz

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3Acfd37c26d969bdd5ee3dfa9a935592062c6803bdbfe5191f0557539facaa818d&amp;source=constructor" width="100%" height="320" frameborder="0"></iframe>

---

Проводятся все виды услуг для предоставления медицинской помощи взрослым больным с заболеваниями крови, включая высокодозную химиотерапию, диагностику, соответствующую международным протоколам. Пациентам отделения доступны консультации узких специалистов разных профилей, лабораторные услуги, необходимые инструментальные исследования, имеется собственная реанимация.

Кроме того, именно в Усть-Каменогорске отлажена система своевременной доступной помощи в области проведения исследований, которые не проводятся на сегодняшний день в Казахстане, в частности, молекулярно-генетические тесты для постановки диагноза и мониторинга эффективности лечения, проведение контрольных иммуногистохимических и гистологических исследований. Для этого Центр имеет партнерские отношения с ведущими клиниками России и дальнего зарубежья.

В команде специалистов филиала работают высококвалифицированные врачи-гематологи, руководит филиалом реаниматолог высшей медицинской категории, проработавший долгое время в Центре кардиохирургии и владеющий всеми методами обеспечения сосудистого доступа, оказания неотложной медицинской помощи.

[Лекарственный формуляр](../../drug-formulary)

{% set city = 'oskemen' %}
{% set lang = page.language %}

<div class="pricelist-controls" style="margin-bottom:12px;">
  <button id="pl-toggle" type="button">Показать прайс</button>
  <span id="pl-status" style="margin-left:8px; font-size:90%; color:#666;"></span>
</div>

<div id="pricelist-box" hidden>
  <div id="pl-spinner" style="display:none;">Загрузка…</div>
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
      btnTgl.textContent = 'Скрыть прайс';
    } else {
      box.setAttribute('hidden', '');
      btnTgl.textContent = 'Показать прайс';
    }
    updateRefreshState();
  });

  
  // Инициализация
  updateRefreshState();
})();
</script>