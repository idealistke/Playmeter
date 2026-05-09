import serial
import sys
import time

arduino = serial.Serial('COM7',9600)
time.sleep(2)

message = sys.argv[1]

arduino.write(message.encode())

arduino.close()