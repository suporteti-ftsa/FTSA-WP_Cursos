import { Checkbox, TextInput } from '@mantine/core';
import React, { useState } from 'react';

function GiftPurchase({
  giftPurchaseData,
  updateGiftPurchaseData,
  giftPurchase,
  toggleGiftPurchase,
  validationErrors,
}) {
  return (
    <div className="eb-checkout__gift-purchase">
      <Checkbox
        label="Purchase for someone else"
        checked={giftPurchase}
        onChange={(e) => toggleGiftPurchase(e.currentTarget.checked)}
      />
      {giftPurchase && (
        <div className="gift-purchase__content">
          <div className="gift-purchase__col-2">
            <TextInput
              label="First name"
              placeholder="First name"
              value={giftPurchaseData.eb_recipient_first_name}
              onChange={(e) =>
                updateGiftPurchaseData({
                  eb_recipient_first_name: e.currentTarget.value,
                })
              }
              error={validationErrors?.eb_recipient_first_name}
            />
            <TextInput
              label="Last name"
              placeholder="Last name"
              value={giftPurchaseData.eb_recipient_last_name}
              onChange={(e) =>
                updateGiftPurchaseData({
                  eb_recipient_last_name: e.currentTarget.value,
                })
              }
              error={validationErrors?.eb_recipient_last_name}
            />
          </div>
          <div className="gift-purchase__col-2">
            <TextInput
              label="Email address"
              placeholder="Email address"
              type="email"
              value={giftPurchaseData.eb_recipient_email}
              onChange={(e) =>
                updateGiftPurchaseData({
                  eb_recipient_email: e.currentTarget.value,
                })
              }
              error={validationErrors?.eb_recipient_email}
            />
            <TextInput
              label={
                <>
                  Phone <span className="optional-label">(optional)</span>
                </>
              }
              placeholder="Phone"
              type="tel"
              value={giftPurchaseData.eb_recipient_phone}
              onChange={(e) =>
                updateGiftPurchaseData({
                  eb_recipient_phone: e.currentTarget.value,
                })
              }
            />
          </div>
        </div>
      )}
    </div>
  );
}

export default GiftPurchase;
