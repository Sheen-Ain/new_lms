a = True
b = False
print(a and b)#False
print(a or b)#True
print(not a)#False

#Q2
num = int(input("Enter a number: "))
if num > 0:
    print("This is positive", num)
elif num < 0:
    print("This is Negative", num)
else:
    print("this is zero", num)

#Q3
fruits = ["apple", "banana", "mango"]
search = input("Enter a fruit: ")
if search in fruits:
    print("This is fruit", search)
else:
    print("This is not a fruit")

#Q4
age = input("enter age?")
if int(age) >= 18:
    print("You can vote",age)
else:
    print("You cannot vote",age)

#Q5
x = 10
y = 20
print(x > y)#False
print(x < y)#True

#Q6
alphabets = input("enter alphabets: ")
vowels = "aeiou"
if alphabets in vowels:
    print("this is a vowel: ", alphabets)
else:
    print("this is not a vowel: ", alphabets)

#Q7
num2 = int(input("enter you number: "))
if num2 % 2 == 0 and num2 > 50:
    print("big even number",num2)
elif num2 != 0 and num2 <= 50:
    print("small even number",num2)
elif num2 %2 == 0 and num2 > 50:
    print("big odd number",num2)
else:
    print("big odd number",num2)

#8

marks = input("enter marks (0-100): ")
if int(marks) > 90:
    print("grade A")
if int(marks) > 95:
        print("Outstanding!")
elif int(marks) > 80:
    print("grade b")
elif int(marks) > 60:
    print("grade c")
else:
    print("fail")


#Q9

students = ["Ali", "Sara", "Ahmed", "Zainab", "Bilal"]
name = input("Please enter your name: ")
if name in students and len(name)>5:
    print("Valid Senior Student" + name)
else:
    print("Invalid Senior Student")


#Q10

temperatue = int(input("enter temperatue: "))
if temperatue > 35:
    print("very hot")
elif temperatue >=25 and temperatue <= 35:
    print("hot")
elif temperatue >=15 and temperatue <= 25:
    print("Pleasant")
elif temperatue >=5 and temperatue <=15:
    print("Cold")
else:
    print("very cool")

#Q11
username = input("Enter your username: ")
password =input("Enter your password: ")
if username == "admin":
    if password == "12345":
        print("Login Successful")
    else:
        print("Wrong Password:")
else:
        print("Invalid Username:")

#Q12
a = float(input("Pehla number (a) enter karein: "))#2
b = float(input("Doosra number (b) enter karein: "))#5
c = float(input("Teesra number (c) enter karein: "))#6
if a > b:
    if a > c:
        print(f"Sabsy bara number hai: a")
    else:
        print(f"Sabsy bada number hai: c")
else:
    if b > c:
        print(f"Sabsy bada number  hai: b")
    else:
        print(f"Sabsy bada number hai: c")#6


#Q13
per = int(input("enter percentage: "))
if per >= 90:
    if per == 100:
        print("A+ Grade")
    else:
        print("A Grade")
elif per >= 80:
    print("B Grade")
elif per >= 70:
    print("C Grade")
else:
    print("Below Average")