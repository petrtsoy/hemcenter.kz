---
title: Астана
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
process:
    markdown: true
    twig: true
visible: true
---

Медицинское подразделение ТОО «Центр гематологии» по г. Астана открыто в 2021 году. Основной задачей является обеспечение взрослого населения качественными консультативно-диагностическими услугами. 

Консультации гематолога доступны в виде очных и дистанционных (заочных и онлайн-консультаций в реальном времени).

---

Очный консультативный прием проводится по адресу:  

**Z05T8A8 г. Астана, ул. Кенесары 4Б (в будние дни с 08:00 до 17:00)**

Запись по телефону / Whatsapp **+7 771 900 08 64, +7 777 532 6515**

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3A15e911218eabcae60fbd8a15dfbdee1e87ea189f6d3c2bd1ec323a0319277c11&amp;source=constructor" width="100%" height="240" frameborder="0"></iframe>

---

Помимо консультативного приема на производственной базе имеется возможность проведения диагностики, включая лабораторное обследование:
* трепанобипосия костного мозга
* пункция костного мозга с подсчетом миелограммы и цитохимическим исследованием
* гистологическое и иммуногистохимическое исследование костного мозга и лимфоузлов
* цитогенетическое исследование костного мозга
* иммунофенотипирование костного мозга и периферической крови
* молекулярно-генетическое исследование костного мозга и периферической крови с широкой панелью маркеров

[Лекарственный формуляр](../../drug-formulary)

{% set city = 'astana' %}
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