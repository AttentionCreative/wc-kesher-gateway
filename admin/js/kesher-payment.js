jQuery(document).ready(function ($) {
  const installmentsCheckbox = $(
    '#woocommerce_kesher_credit_card_installments'
  );
  const insRulesField = $('#woocommerce_kesher_credit_card_ins_rules');

  if (!installmentsCheckbox.length || !insRulesField.length) return;

  let ruleInitiator = $('<button>', {
    id: 'rule-initiator',
    text: 'הוספת כלל חדש +',
    type: 'button',
    class: 'button button-primary',
    css: { margin: '15px 0' },
  }).insertAfter(insRulesField.parent());

  /**
   * פונקציה לרנדר חוקים קיימים מהשדה ins_rules
   */
  function renderRulesHtml() {
    let rules = insRulesField.val();

    if (rules) {
      try {
        rules = JSON.parse(rules);
        $('.rule-wrapper').remove(); // ניקוי חוקים קיימים

        rules.forEach((rule) => {
          let ruleHTML = `
                      <div class="rule-wrapper" style="display: flex; align-items: center; width: 50%; margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border: 1px solid #ddd; gap: 15px;">

                          <div style="flex: 1;">
                              <label>מ-</label>
                              <input type="number" min="1" class="rule-from" placeholder="מ-" value="${rule.from}" style="width: 200px; padding: 8px;">
                          </div>

                          <div style="flex: 1;">
                              <label>עד</label>
                              <input type="number" min="1" class="rule-to" placeholder="עד" value="${rule.to}" style="width: 200px; padding: 8px;">
                          </div>

                          <div style="flex: 1;">
                              <label>תשלומים</label>
                              <input type="number" min="1" class="rule-installments" placeholder="תשלומים" value="${rule.installments}" style="width: 200px; padding: 8px;">
                          </div>

                          <button class="rule-destroyer button button-secondary" style="background-color: #dc3545; color: #fff; padding: 8px 15px;">מחיקה</button>
                      </div>
                  `;
          ruleInitiator.before(ruleHTML);
        });
      } catch (e) {
        console.error('JSON Parsing Error in ins_rules:', e);
      }
    }
  }

  /**
   * פונקציה לעדכון הנתונים בשדה ins_rules
   */
  function updateRuleData() {
    const ruleData = [];

    $('.rule-wrapper').each(function () {
      const $wrapper = $(this);
      const from = parseInt($wrapper.find('.rule-from').val()) || 0;
      const to = parseInt($wrapper.find('.rule-to').val()) || 0;
      const installments =
        parseInt($wrapper.find('.rule-installments').val()) || 1;

      if (from > 0 && to > 0 && installments > 0) {
        ruleData.push({ from, to, installments });
      }
    });

    insRulesField.val(JSON.stringify(ruleData)).trigger('change');
  }

  /**
   * הצגת/הסתרת כפתור וחוקים בהתאם למצב הצ'קבוקס
   */
  installmentsCheckbox.on('change', function () {
    const isChecked = $(this).is(':checked');
    ruleInitiator.toggle(isChecked);

    if (isChecked) {
      renderRulesHtml();
    } else {
      $('.rule-wrapper').remove();
      insRulesField.val('');
    }
  });

  /**
   * אתחול במצב טעינת העמוד
   */
  if (installmentsCheckbox.is(':checked')) {
    ruleInitiator.show();
    renderRulesHtml();
  } else {
    ruleInitiator.hide();
  }

  /**
   * הוספת כלל חדש
   */
  $(document).on('click', '#rule-initiator', function (e) {
    e.preventDefault();

    let newRuleHTML = `
          <div class="rule-wrapper" style="display: flex; align-items: center; width: 50%; margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border: 1px solid #ddd; gap: 15px;">
              
              <div style="flex: 1;">
                  <label>מ-</label>
                  <input type="number" min="1" class="rule-from" placeholder="מ-" style="width: 200px; padding: 8px;">
              </div>

              <div style="flex: 1;">
                  <label>עד</label>
                  <input type="number" min="1" class="rule-to" placeholder="עד" style="width: 200px; padding: 8px;">
              </div>

              <div style="flex: 1;">
                  <label>תשלומים</label>
                  <input type="number" min="1" class="rule-installments" placeholder="תשלומים" style="width: 200px; padding: 8px;">
              </div>

              <button class="rule-destroyer button button-secondary" style="background-color: #dc3545; color: #fff; padding: 8px 15px;">מחיקה</button>
          </div>
      `;

    $(this).before(newRuleHTML);
  });

  /**
   * מחיקת כלל
   */
  $(document).on('click', '.rule-destroyer', function () {
    $(this).closest('.rule-wrapper').remove();
    updateRuleData();
  });

  /**
   * עדכון נתונים בזמן שינוי הקלטים
   */
  $(document).on(
    'input',
    '.rule-from, .rule-to, .rule-installments',
    function () {
      updateRuleData();
    }
  );
});
