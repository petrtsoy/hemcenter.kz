---
title: 'Astana branch'
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
visible: true
process:
    markdown: true
    twig: true
---

The medical department of LLP “Center of Hematology” in Astana was established in 2021. Its main objective is to provide the adult population with high-quality consultative and diagnostic medical services. 
Consultations with a hematologist are available both in person and remotely — including online real-time consultations and asynchronous (off-site) consultations.

---

In-person consultations are conducted at: 
**Z05T8A8 Astana, 4B Kenesary Street (weekdays from 08:00 to 17:00)**

Appointments can be made via phone or WhatsApp:
**📞 +7 771 900 08 64, +7 777 532 65 15**

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3A15e911218eabcae60fbd8a15dfbdee1e87ea189f6d3c2bd1ec323a0319277c11&amp;source=constructor" width="100%" height="240" frameborder="0"></iframe>

---

In addition to consultations, the department provides a wide range of diagnostic services, including laboratory examinations, such as:
* Trephine biopsy of bone marrow;
* Bone marrow aspiration with myelogram counting and cytochemical testing;
* Histological and immunohistochemical examination of bone marrow and lymph nodes;
* Cytogenetic analysis of bone marrow;
* Immunophenotyping of bone marrow and peripheral blood;
* Molecular genetic testing of bone marrow and peripheral blood using an extended panel of markers.

[Drug Formulary](../../drug-formulary)

{% set city = 'astana' %}
{% set lang = page.language %}

<div class="pricelist-controls" style="margin-bottom:12px;">
  <button id="pl-toggle" type="button">Show pricelist</button>
  <span id="pl-status" style="margin-left:8px; font-size:90%; color:#666;"></span>
</div>

<div id="pricelist-box" hidden>
  <div id="pl-spinner" style="display:none;">Loading…</div>
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
      btnTgl.textContent = 'Hide pricelist';
    } else {
      box.setAttribute('hidden', '');
      btnTgl.textContent = 'Show pricelist';
    }
    updateRefreshState();
  });

  
  // Инициализация
  updateRefreshState();
})();
</script>