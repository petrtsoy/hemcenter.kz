# Halyk epay: check-status и paymentType

Разбор 20.11.2026. epay добавляет в ответ check-status поле `transaction.paymentType` (String:
ApplePay, CardID, HalykId, PAN, QR, Credit, LOAN, INSTALLMENT и др.), бой – ориентировочно 14.12.2026.

Правки не обязательны: `checkPaymentStatus()` (`user/plugins/online-order-fs/online-order-fs.php`)
возвращает JSON как есть, `apiPaymentConfirm()` читает только `resultCode` ('100') и
`transaction.statusName` (CHARGE/AUTH); лишние поля проходят мимо.

- [ ] (по желанию) Сохранять `paymentType` и блок `transaction` в `$row['payment']` при подтверждении
      через API-фоллбэк (сейчас там только `payment_status` и `payment_api_check`, а у колбэка весь
      запрос лежит в `payment.raw`). Нужно для разбора споров: LOAN/INSTALLMENT по возвратам ведут
      себя не как карта. 3–4 строки.
- [ ] Проверить боевой оверрайд `user/config/plugins/online-order-fs.yaml` на сервере: `status_url`
      должен быть `https://epay-api.homebank.kz/check-status/payment/transaction` (в репо –
      тестовые адреса `test-epay-api.epayment.kz`), иначе фоллбэк молча получает null.
