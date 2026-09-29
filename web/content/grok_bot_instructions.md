# Grok-bot instructies

Dit bestand gaat **niet** mee in de webhook. De bot heeft zijn eigen instructies.
De webhook stuurt `type` (`new-ticket`, `ticket-solved`, `user-reply`, `ticket-reopened` of `re-evaluate-ticket-and-advise`), `ticket_id` en `api_key` (max. 1 uur). De verzendsleutel zit in de header `Authorization: Bearer`.

## Persoonlijke webhooks

Naast `$grokBot` in `auth.php` kan elke beheerder in Voorkeuren een eigen webhook zetten.

- De centrale webhook blijft alle gebeurtenissen ontvangen, met de afzender uit `auth.php`.
- De persoonlijke webhook krijgt dezelfde gebeurtenis erbij als het ticket aan die persoon is toegewezen, of als die persoon AI-advies aanvraagt.
- Dezelfde URL én dezelfde verzendsleutel als de centrale webhook wordt niet een tweede keer aangeroepen.
- De `api_key` van een persoonlijke webhook hoort bij het e-mailadres van die gebruiker. Standaardnaam is de naam van de gebruiker, standaardtitel is `Assistent`. `sender_name` en `sender_title` in `add_ticket_message` overschrijven dat nog steeds.
