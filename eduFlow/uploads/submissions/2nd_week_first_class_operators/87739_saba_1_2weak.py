#1st weak
name = input("Enter your name:")
age = input("Enter your age:")
City = input("Enter your city:")
print("Name:",name)
print("Age:",age)
print("City",City)


#combine name and City
print("my name is", name,"and i live in",City)


#sum, multiply,divide
num1 = float(input("Enter first number:"))
num2 = float(input("Enter second number:"))

print("sum:", num1 + num2)
print("multiply:", num1 * num2)
print("divide:", num1 / num2)


#introduction program
name = input("Enter your name:")
Rollno = input("Enter your roll no:")
department = input("Enter your department:")
Universityname = input("Enter your university name:")
print("my name is:",name,
"my Rollno is:",Rollno ,
" my department is:", department,
"my university name is:", Universityname)

#day2
#age string
age = input ("Enter your age:")
age = int(age)
print(age, type(age))

#integer
num = 10
num = float(num)
print(num, type(num))

#float
num = 10.5
num = int(num)
print(num, type(num))

#variable check
x = 7
print(type(x))

#negative num
num = -10
print(num)

#Scientific notation
num = 2e3
print(num)

#string + number
name = "age is"
age = 19
print (name+ str(age))

#day3
#Fruit list
fruits = ["apple", "banana", "cherry", "mango", "orange", "grapes"]
print(fruits)

#fruit addition
fruits.append("pineapple")
print(fruits)

#removal of fruit
fruits.remove("banana")
print(fruits)

#first element
print(fruits[0])

#last element
print(fruits[-1])

#list slicing
print(fruits[1:4])

#sum of numbers
numbers = [10,20,30,40,50]
print(sum(numbers))

#INDEX AND POP
index = int(input("Enter index to remove:"))
fruits.pop(index)
print(fruits)

#day4
#one tuple
my_tuple = (10, 20, 30, 40, 50)
print(my_tuple)

#print tuple first element
print(my_tuple[0])

#tuple slicing
print(my_tuple[1:4])   # 20, 30, 40

#one string print 5times
text = "Hello"
print(text * 5)

#one name print in uppercase
name = input("Enter your name: ")
print(name.upper())

#one name print in lowercase
name = input("Enter your name: ")
print(name.lower())

#print multi line string
multi_line = """This is line 1
This is line 2
This is line 3"""
print(multi_line)

#combine two strings
str1 = "Hello"
str2 = "World"
result = str1 + " " + str2
print(result)

#day5
#dictionary
student = {
    "name": "Saba Rashid",
    "age": 19,
    "class": "Bs:IT"
}
print(student)

#print dictionary keys
print(student.keys())

#print dictionary values
print(student.values())

#print dictionary items
print(student.items())

#print values
key = input("Enter key(name, age,class")
print(student.get(key))

#add new data
student["city"] = "Karachi"
print(student)

#remove one item
student.pop("age")
print(student)

#combine two dictionaries
dict1 = {"a": 1,"b":2}
dict2 = {"c": 3, "d":4}
combined = {**dict1, **dict2}
print(combined)


#2nd weak
#arithmetic operators
#1.
from operator import length_hint

a=15
b=4
print(a + b,a - b,a * b,a/b,a//b,a%b,a**b)

#2.
print(17 // 3) #integer division
print(type(17//3))
print(17/3) #decimal float division
print(type(17/3))

#3.
print(5**3)
print(2**4)

#4.


#5.
length = 6
width = 8
area = length * width
print("area:", area)

#6.
print(10+5*2)
print((10+5)*2)

#7.
print(9%2)
print(10%2)
print(11%2)
#9%2 is odd
#10%2 is odd

#8.
print(100//7)
print(100%7)

#9
base = int(input("Enter base: "))
exp = int(input("Enter exponent: "))
print(base ** exp)

#10
print((-5)**2)
print(-5**2)

#11
x=10
x+= 5
x-=3
x*=2
print(x)

#12
a = 20
a //=3
a %=4
print(a)

#13
#y = y * 3,
#y *= 3

#14
score = 50
score+=10
score*=2
score-=15
print(score)

#15
num=7
num*=5
num/=2
print(num)

#16
z=100
z**=2
z//=10
print(z)

#17
p=5
p +=p
print(p)

#18
num=8
num+=10
print(num)

#19
a=10
b=3
a*=b
a+=5
print(a)

#20
x,y,z=5,10,15
x+=y
z*=2
print(x,y,z)

#21
10==10
10!=5
7>3
4<8
5>=5
6<=10
print(10==10)
print(10!=5)
print(7>3)
print(4<8)
print(5>=5)
print(6<=10)

#22
a=15
b=20
print(a>b)
print(a<=b)

#23
age = int(input("Enter age: "))
print(age >= 18)

#24
print(5=="5")

#25
x=10
y=10.0
print(x==y)
print(x is y)

#26
a=7
b=5
print(a<b)

#27
result = (10+5)>(3*4)

#28
a=7
b=7
print(a==b)
print(a>=b)




