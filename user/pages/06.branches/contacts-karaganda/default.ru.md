---
title: Караганда
hide_page_title: false
visible: true
show_sidebar: true
hide_git_sync_repo_link: false
process:
    markdown: true
    twig: true
---

Карагандинский филиал был открыт в 2018 году в рамках реализации государственно-частного партнерства между акиматом Карагандинской области, Управлением здравоохранения Карагандинской области и Центром гематологии. 

---

**пр. С.Сейфуллина, дом 17, второй этаж**

тел. **+7 747 095 5650** (будни с 08:00 до 17:00)

karaganda@hemcenter.kz

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3Ae290cff7866c72ac63514d64fc910a9b640d860cd214d346c171ff654fcc2d0e&amp;source=constructor" width="100%" height="240" frameborder="0"></iframe>

---

Основными задачами государственно-частного партнерства являются 
* создание уникального высококвалифицированного стационарного комплекса для оказания медицинских услуг в области взрослой гематологии,
* интегрирование системы динамического наблюдения пациентов с заболеваниями крови на амбулаторном уровне, раннего их выявления, диспансеризации, 
* а также оказание медицинской помощи пациентам иных профилей, имеющих вторичные симптомы патологии системы крови.

В филиале работают квалифицированные гематологи, помощники врача, внедрена уникальная информационная система, позволяющая осуществлять поддержку принятия решений в области клинической гематологии, вести учет всех технологий, обеспечивая безопасность пациента. 

Персонал в центре выполняет все виды медицинских услуг, диагностических и лечебных мероприятий, включая генетическую диагностику, иммуногистохимию костного мозга, используя партнерские отношения с ведущими зарубежными клиниками, в частности с РосНИИ гематологии и трансфузиологии в Санкт-Петербурге, ФГБУ «НМИЦ гематологии» в Москве. 

Пациенты, получающие медицинскую помощь в Центре, имеют возможность доступа к услугам Областной клинической больницы, что позволяет предоставлять все необходимые виды услуг инструментальной и лабораторной диагностики и проводить консультации специалистов других профилей.

В амбулаторном кабинете Центра ведётся диспансерный и первичный приём пациентов и оказывается услуга виртуальной консультации для пациентов и врачей любых специальности Карагандинской области.

[Лекарственный формуляр](../../drug-formulary)

{% set city = 'karaganda' %}
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
  var lang    = {{ lang|json_encode|raw }};

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