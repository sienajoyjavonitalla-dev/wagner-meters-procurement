# Procurement Research App – Big Picture Flow

A simple overview of what the app does and how data moves through it.

---

## What Problem It Solves

You buy thousands of parts from many suppliers. Some are big distributors (DigiKey, Mouser, etc.), others are smaller vendors. Prices change over time, and you don’t always renegotiate or check the market. This app **automatically checks whether current market prices are lower than what you last paid** (at the right quantity) and surfaces those opportunities so you can renegotiate or switch suppliers.

---

## The Flow in Simple Words

1. **You bring in your data**  
   You provide an inventory/pricing file (Excel or CSV): what parts you have, who you bought them from, how much you paid, and when. Optionally you also provide lists of “priority” vendors or items with big price spreads.

2. **The app decides what to check**  
   It builds a **research queue**: a list of items and suppliers worth checking. It focuses on things like top spend, recent purchases, and items where pricing varies a lot between sources.

3. **It maps your parts to catalog part numbers**  
   Your files often use internal part IDs. The app uses a mapping file (and some rules) to translate those into **manufacturer part numbers (MPNs)** that distributors understand. Without a good mapping, it can’t reliably look up prices.

4. **It asks distributors for current prices**  
   For each item in the queue, the app calls **DigiKey**, **Mouser**, and **Nexar** (Octopart) APIs with the MPN. Each API returns that distributor’s current price (and sometimes alternate parts). The app only uses providers you’ve configured with API keys.

5. **It compares and picks the best**  
   For each item it compares:  
   - Your last purchase price  
   - The prices returned by each distributor  
   It figures out the best available price, whether you’d save money by switching or renegotiating, and how confident the match is.

6. **If APIs don’t find a match, it can ask AI**  
   When no distributor returns a good match, the app can call an AI (e.g. Claude) with the part description and ask for suggested part numbers or alternates. That gives you a starting point for manual research.

7. **You see results in a dashboard**  
   All of this is summarized in a dashboard: which items have lower prices elsewhere, estimated savings, which provider had the hit, and what still needs manual mapping or research. You use that to decide what to renegotiate or where to buy.

---

## One-Line Summary

**Upload your inventory and purchase history → app checks DigiKey, Mouser, and Nexar for current prices → dashboard shows where you can save money.**

---

## Do We Ask the Boss for API Keys or Create Them Ourselves?

**Recommendation: align with your boss first, then either they provide keys or you create them with company approval.**

- **DigiKey**  
  - API access is via their [Developer Portal](https://developer.digikey.com/).  
  - You can sign up yourself, but the account is often tied to a company (e.g. company email).  
  - **Ask the boss:** Does the company already have a DigiKey developer account or purchasing account we should use? If yes, they may provide `DIGIKEY_CLIENT_ID` and `DIGIKEY_CLIENT_SECRET`. If not, get approval to register using a company email and then create the keys yourself.

- **Mouser**  
  - API keys come from Mouser’s API program (signup on their site).  
  - Same idea: could be a shared company account or a new one.  
  - **Ask the boss:** Do we have Mouser API access already? If not, get approval to sign up and create `MOUSER_API_KEY` (or `MOUSER_SEARCH_API_KEY`) yourself.

- **Nexar (Octopart)**  
  - Nexar/Octopart has a developer program; you get `NEXAR_CLIENT_ID` and `NEXAR_CLIENT_SECRET` from their portal.  
  - **Ask the boss:** Same as above—confirm if the company has credentials. If not, get approval to register and create the client ID/secret.

**In short:** Ask the boss whether the company already has API access for these three. If yes, request the keys (or access to the account). If no, get approval to sign up with a company email and create the keys yourself; then store them securely (e.g. in `.env`, never in git) and document where they’re used so the boss knows.
