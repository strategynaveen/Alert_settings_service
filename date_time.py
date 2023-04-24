import datetime


current_datetime = datetime.datetime.now()
#current_datetime = unicode(now.replace(microsecond=0))
current_date_time = current_datetime.strftime("%Y-%m-%dT%H:00:00")
previous_date_Time = datetime.datetime.now() - datetime.timedelta(hours = 1)
previous_date_Time = previous_date_Time.strftime("%Y-%m-%dT%H:00:00")


print(type(current_date_time))
print("previous time")
print(previous_date_Time)

#print(datetime.datetime.strptime(current_date_time,'%Y-%M-%d').timestamp())

past_date_time = datetime.datetime.now() - datetime.timedelta(hours = 5)
print("printing date time")
print(past_date_time)
