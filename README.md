# Update script for Mining Rig Rentals Rigs

The script updates an algo rig price for given currency (BTC. LTC. etc.) getting the suggested price from MRR and applying a modifier.

### Usage: <br />
Update mrr_config.php with your api and secret from MRR <br />

php mrr.php --algo=scrypt --currency=LTC --modifier=0.03 --th=mh --updatePrice=true --rigID=12345

--th 
- mh for scrypt
- th for sha256

<br /><br />

BTC: 1LWgH48X6uC9JN4bbvxViB3nSkU5S2yJZW

LTC: LSgGfzuf3QErkX4mFkeMDwckdH3CEYqycf