import logging
import time




def print_msg():
    # logging.basicConfig( level=logging.DEBUG)
    logging.basicConfig(filename='example.log', format='%(asctime)s %(message)s', level=logging.DEBUG)
    logging.debug('This message should go to the log file')
    logging.info('So should this')
    logging.warning('And this, too')
    logging.error('And non-ASCII stuff, too, like Øresund and Malmö')

i=0
while True:
    i = i+1
    print("count",i)
    print_msg()
    time.sleep(60)